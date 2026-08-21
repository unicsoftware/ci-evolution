<?php

namespace App\Models;

use CodeIgniter\Model;

class WhatsappModel extends Model
{
    protected $table            = 'whatsapp_configs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;

    protected $allowedFields = [
        'empresa_id',
        'usuario_id',
        'api_url',
        'api_key',
        'name',
        'external_id',
        'instance_name',
        'phone',
        'profile_name',
        'profile_picture',
        'status',
        'connected',
        'last_connection'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'empresa_id'    => 'required|integer',
        'usuario_id'    => 'required|integer',
        'api_url'       => 'required|valid_url',
        'api_key'       => 'required|min_length[5]',
        'instance_name' => 'required|min_length[1]|alpha_dash',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Saves or updates config for a company and user.
     *
     * @param array $data
     * @return int|bool
     */
    public function saveConfig(array $data)
    {
        if (isset($data['id'])) {
            if ($this->update($data['id'], $data)) {
                return $data['id'];
            }
            return false;
        }
        return $this->insert($data);
    }

    /**
     * Gets config for a company and user.
     *
     * @param int $empresaId
     * @param int $usuarioId
     * @return array|null
     */
    public function getConfig(int $empresaId, int $usuarioId)
    {
        return $this->where('empresa_id', $empresaId)
                    ->where('usuario_id', $usuarioId)
                    ->first();
    }

    /**
     * Gets all configs for a company.
     *
     * @param int $empresaId
     * @return array
     */
    public function getAllByTenant(int $empresaId)
    {
        return $this->where('empresa_id', $empresaId)->findAll();
    }

    /**
     * Counts the number of connections for a company.
     *
     * @param int $empresaId
     * @return int
     */
    public function countByTenant(int $empresaId)
    {
        return $this->where('empresa_id', $empresaId)->countAllResults();
    }

    /**
     * Updates only the status field.
     *
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function updateStatus(int $id, string $status)
    {
        return $this->update($id, ['status' => $status]);
    }

    /**
     * Updates connection properties.
     *
     * @param int $id
     * @param bool $connected
     * @param string|null $lastConnection
     * @return bool
     */
    public function updateConnection(int $id, bool $connected, ?string $lastConnection = null)
    {
        $data = ['connected' => $connected];
        if ($connected) {
            $data['last_connection'] = $lastConnection ?? date('Y-m-d H:i:s');
        }
        return $this->update($id, $data);
    }

    /**
     * Updates profile name, picture and phone.
     *
     * @param int $id
     * @param array $profileData
     * @return bool
     */
    public function updateProfile(int $id, array $profileData)
    {
        $data = [];
        if (array_key_exists('phone', $profileData)) {
            $data['phone'] = $profileData['phone'];
        }
        if (array_key_exists('profile_name', $profileData)) {
            $data['profile_name'] = $profileData['profile_name'];
        }
        if (array_key_exists('profile_picture', $profileData)) {
            $data['profile_picture'] = $profileData['profile_picture'];
        }

        if (!empty($data)) {
            return $this->update($id, $data);
        }
        return false;
    }

    /**
     * Alias for getConfig.
     *
     * @param int $empresaId
     * @param int $usuarioId
     * @return array|null
     */
    public function getByUser(int $empresaId, int $usuarioId)
    {
        return $this->getConfig($empresaId, $usuarioId);
    }
}
