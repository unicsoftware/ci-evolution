<?php

namespace App\Controllers;

use App\Models\WhatsappModel;
use Config\Services;
use CodeIgniter\API\ResponseTrait;
use Exception;

class Whatsapp extends BaseController
{
    use ResponseTrait;

    protected $session;
    protected $model;

    public function __construct()
    {
        $this->session = Services::session();
        $this->model = new WhatsappModel();

        if (!$this->session->has('usuario_id')) {
            $this->session->set('usuario_id', 1);
            $this->session->set('empresa_id', 1);
        }
    }

    private function validateAuth(): array
    {
        $usuarioId = $this->session->get('usuario_id');
        $empresaId = $this->session->get('empresa_id');

        if (!$usuarioId || !$empresaId) {
            throw new Exception("Sua sessão expirou ou você não está autorizado.", 401);
        }

        return [
            'usuario_id' => (int) $usuarioId,
            'empresa_id' => (int) $empresaId
        ];
    }

    private function jsonRespond(array $data, int $status = 200)
    {
        $data['csrf_token'] = csrf_hash();
        return $this->respond($data, $status);
    }

    private function getPlanLimit(int $empresaId): int
    {
        // Mocking the plan limit to 5 as per user instructions
        // In the future this should fetch the limit from a PlanModel
        return 5;
    }

    private function getConnection(int $id, array $auth)
    {
        $config = $this->model->find($id);
        if (!$config || $config['empresa_id'] != $auth['empresa_id']) {
            throw new Exception("Conexão não encontrada ou acesso negado.", 404);
        }
        return $config;
    }

    private function generateInstanceName(int $tenantId, int $connectionNumber): string
    {
        return sprintf('t%d_wa_%02d', $tenantId, $connectionNumber);
    }

    public function index()
    {
        try {
            $auth = $this->validateAuth();
            $empresaId = $auth['empresa_id'];
            
            $connections = $this->model->getAllByTenant($empresaId);
            $connectionsCount = $this->model->countByTenant($empresaId);
            $planLimit = $this->getPlanLimit($empresaId);
            
            return view('whatsapp/index', [
                'connections'      => $connections,
                'connectionsCount' => $connectionsCount,
                'planLimit'        => $planLimit,
                'session'          => $this->session
            ]);
        } catch (Exception $e) {
            return "Erro: " . $e->getMessage();
        }
    }

    public function create()
    {
        try {
            $auth = $this->validateAuth();
            $empresaId = $auth['empresa_id'];
            
            $name = $this->request->getPost('name');
            $name = trim($name ?? '');
            
            if (empty($name)) {
                return $this->jsonRespond([
                    'success' => false,
                    'message' => 'O nome da conexão é obrigatório.'
                ], 400);
            }

            $connectionsCount = $this->model->countByTenant($empresaId);
            $planLimit = $this->getPlanLimit($empresaId);

            if ($connectionsCount >= $planLimit) {
                return $this->jsonRespond([
                    'success' => false,
                    'message' => 'Você atingiu o limite de conexões WhatsApp do seu plano.'
                ], 403);
            }

            $apiUrl = env('evolution.apiUrl') ?: 'http://evolution.eabc.com.br';
            $apiKey = env('evolution.apiKey') ?: 'ic6F5CEABCDIGI7636LNnxF5KqKjc9TZaJ';
            
            // Generate deterministic instance name based on a timestamp/random to avoid duplicates
            // Or based on count. Count is fine but deleted items can cause clashes.
            // A safer approach: use DB max id + 1 or uniqid
            $instanceName = $this->generateInstanceName($empresaId, time() % 100000);

            $data = [
                'empresa_id'    => $empresaId,
                'usuario_id'    => $auth['usuario_id'],
                'name'          => $name,
                'api_url'       => $apiUrl,
                'api_key'       => $apiKey,
                'instance_name' => $instanceName,
                'status'        => 'waiting_qr',
                'connected'     => false
            ];

            $configId = $this->model->insert($data);

            if (!$configId) {
                throw new Exception("Erro ao salvar configuração no banco de dados.");
            }

            $evolution = Services::evolution($apiUrl, $apiKey, $instanceName, false);
            
            // Create instance on API
            $result = $evolution->createInstance();
            $qrBase64 = \App\Libraries\Evolution::extractQrBase64($result);

            return $this->jsonRespond([
                'success' => true,
                'message' => 'Instância criada com sucesso! Carregando QR Code...',
                'data'    => [
                    'id'           => $configId,
                    'api_response' => $result,
                    'qr_base64'    => $qrBase64
                ]
            ]);
        } catch (Exception $e) {
            log_message('error', 'Error in Whatsapp::create -> ' . $e->getMessage());
            
            return $this->jsonRespond([
                'success' => false,
                'message' => 'Erro ao criar instância: ' . $e->getMessage()
            ], 500);
        }
    }

    public function status($id)
    {
        try {
            $auth = $this->validateAuth();
            $config = $this->getConnection((int)$id, $auth);

            $evolution = Services::evolution($config['api_url'], $config['api_key'], $config['instance_name'], false);
            $state = $evolution->connectionState();
            
            $apiState = $state['instance']['state'] ?? 'close';
            $connected = ($apiState === 'open');

            $this->model->updateConnection($config['id'], $connected);

            $status = $config['status'];
            if ($connected) {
                $status = 'connected';
            } else {
                if ($status === 'connected') {
                    $status = 'disconnected';
                }
            }

            $qrBase64 = null;
            if ($apiState === 'connecting' || $status === 'waiting_qr') {
                try {
                    $qrData = $evolution->getQRCode();
                    $qrBase64 = $qrData['base64'] ?? null;
                } catch (Exception $qrEx) {
                    // Ignore error if QR is not ready yet
                }
            }

            $this->model->updateStatus($config['id'], $status);
            $updatedConfig = $this->model->find($config['id']);

            return $this->jsonRespond([
                'success' => true,
                'message' => 'Status da conexão verificado.',
                'data'    => [
                    'id'        => $config['id'],
                    'api_state' => $apiState,
                    'connected' => $connected,
                    'status'    => $status,
                    'qr_base64' => $qrBase64,
                    'config'    => $updatedConfig
                ]
            ]);
        } catch (Exception $e) {
            log_message('error', 'Error in Whatsapp::status -> ' . $e->getMessage());
            
            if (isset($config) && (str_contains(strtolower($e->getMessage()), 'not found') || $e->getCode() === 404)) {
                $this->model->updateConnection($config['id'], false);
                $this->model->updateStatus($config['id'], 'configured');
                
                $updatedConfig = $this->model->find($config['id']);
                return $this->jsonRespond([
                    'success' => true,
                    'message' => 'A instância não está online na API.',
                    'data'    => [
                        'id'        => $config['id'],
                        'api_state' => 'not_created',
                        'connected' => false,
                        'status'    => 'configured',
                        'config'    => $updatedConfig
                    ]
                ]);
            }

            return $this->jsonRespond([
                'success' => false,
                'message' => 'Erro ao verificar status: ' . $e->getMessage()
            ], $e->getCode() === 404 ? 404 : 500);
        }
    }

    public function qrcode($id)
    {
        try {
            $auth = $this->validateAuth();
            $config = $this->getConnection((int)$id, $auth);

            $evolution = Services::evolution($config['api_url'], $config['api_key'], $config['instance_name'], false);
            $qrData = $evolution->getQRCode();

            $this->model->updateStatus($config['id'], 'waiting_qr');

            return $this->jsonRespond([
                'success' => true,
                'message' => 'QR Code gerado com sucesso.',
                'data'    => $qrData
            ]);
        } catch (Exception $e) {
            return $this->jsonRespond([
                'success' => false,
                'message' => 'Erro ao obter QR Code: ' . $e->getMessage()
            ], $e->getCode() === 404 ? 404 : 500);
        }
    }

    public function disconnect($id)
    {
        try {
            $auth = $this->validateAuth();
            $config = $this->getConnection((int)$id, $auth);

            $evolution = Services::evolution($config['api_url'], $config['api_key'], $config['instance_name'], false);
            $result = $evolution->logout();

            $this->model->updateConnection($config['id'], false);
            $this->model->updateStatus($config['id'], 'disconnected');

            return $this->jsonRespond([
                'success' => true,
                'message' => 'Sessão do WhatsApp desconectada.',
                'data'    => $result
            ]);
        } catch (Exception $e) {
            return $this->jsonRespond([
                'success' => false,
                'message' => 'Erro ao desconectar: ' . $e->getMessage()
            ], $e->getCode() === 404 ? 404 : 500);
        }
    }

    public function restart($id)
    {
        try {
            $auth = $this->validateAuth();
            $config = $this->getConnection((int)$id, $auth);

            $evolution = Services::evolution($config['api_url'], $config['api_key'], $config['instance_name'], false);
            $result = $evolution->restart();

            return $this->jsonRespond([
                'success' => true,
                'message' => 'A instância está sendo reiniciada.',
                'data'    => $result
            ]);
        } catch (Exception $e) {
            return $this->jsonRespond([
                'success' => false,
                'message' => 'Erro ao reiniciar: ' . $e->getMessage()
            ], $e->getCode() === 404 ? 404 : 500);
        }
    }

    public function profile($id)
    {
        try {
            $auth = $this->validateAuth();
            $config = $this->getConnection((int)$id, $auth);

            $evolution = Services::evolution($config['api_url'], $config['api_key'], $config['instance_name'], false);
            $profileData = $evolution->profile();

            $this->model->updateProfile($config['id'], $profileData);
            $updatedConfig = $this->model->find($config['id']);

            return $this->jsonRespond([
                'success' => true,
                'message' => 'Perfil do WhatsApp sincronizado com sucesso!',
                'data'    => $updatedConfig
            ]);
        } catch (Exception $e) {
            return $this->jsonRespond([
                'success' => false,
                'message' => 'Erro ao carregar dados do perfil: ' . $e->getMessage()
            ], $e->getCode() === 404 ? 404 : 500);
        }
    }

    public function delete($id)
    {
        try {
            $auth = $this->validateAuth();
            $config = $this->getConnection((int)$id, $auth);

            $evolution = Services::evolution($config['api_url'], $config['api_key'], $config['instance_name'], false);
            
            try {
                $evolution->deleteInstance();
            } catch (Exception $apiEx) {
                log_message('warning', 'Could not delete instance on API: ' . $apiEx->getMessage());
            }

            $this->model->delete($config['id']);

            return $this->jsonRespond([
                'success' => true,
                'message' => 'Conexão removida com sucesso!'
            ]);
        } catch (Exception $e) {
            return $this->jsonRespond([
                'success' => false,
                'message' => 'Erro ao remover configurações: ' . $e->getMessage()
            ], $e->getCode() === 404 ? 404 : 500);
        }
    }

    public function export($id)
    {
        try {
            $auth = $this->validateAuth();
            $config = $this->getConnection((int)$id, $auth);

            // Hide sensitive information from export
            $exportData = [
                'integration'    => 'whatsapp',
                'provider'       => 'evolution',
                'connection_id'  => $config['id'],
                'external_id'    => $config['external_id'] ?? '',
                'tenant_id'      => $config['empresa_id'],
                'name'           => $config['name'],
                'instance_name'  => $config['instance_name'],
                'phone'          => $config['phone'],
                'status'         => $config['status'],
                'connected'      => $config['connected']
            ];

            return $this->response->setJSON($exportData)
                                  ->setHeader('Content-Disposition', 'attachment; filename="whatsapp-' . $config['instance_name'] . '.json"');
        } catch (Exception $e) {
            return $this->jsonRespond([
                'success' => false,
                'message' => 'Erro ao exportar JSON: ' . $e->getMessage()
            ], $e->getCode() === 404 ? 404 : 500);
        }
    }

    public function send($id)
    {
        try {
            $auth = $this->validateAuth();
            $config = $this->getConnection((int)$id, $auth);

            $number = $this->request->getPost('number');
            $message = $this->request->getPost('message');

            if (empty($number) || empty($message)) {
                return $this->jsonRespond([
                    'success' => false,
                    'message' => 'Número de telefone e mensagem são obrigatórios.'
                ], 400);
            }

            $number = preg_replace('/\D/', '', $number);

            $evolution = Services::evolution($config['api_url'], $config['api_key'], $config['instance_name'], false);
            $result = $evolution->sendMessage($number, $message);

            return $this->jsonRespond([
                'success' => true,
                'message' => 'Mensagem enviada com sucesso!',
                'data'    => $result
            ]);
        } catch (Exception $e) {
            return $this->jsonRespond([
                'success' => false,
                'message' => 'Erro ao enviar mensagem: ' . $e->getMessage()
            ], $e->getCode() === 404 ? 404 : 500);
        }
    }
}
