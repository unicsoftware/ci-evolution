<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de WhatsApp - Evolution API</title>
    
    <meta name="csrf-token" content="<?= csrf_hash() ?>">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom Theme Styles -->
    <link rel="stylesheet" href="<?= base_url('assets/css/whatsapp.css') ?>">
</head>
<body>

    <!-- Loader Overlay -->
    <div id="loader-overlay" class="loading-overlay">
        <div class="loading-box">
            <div class="spinner-border text-primary mb-3" style="width: 3.5rem; height: 3.5rem;" role="status"></div>
            <h4 class="text-white fw-bold mb-1">Processando requisição...</h4>
            <p class="text-secondary mb-0 small">Por favor, aguarde alguns instantes.</p>
        </div>
    </div>

    <!-- Header Navigation -->
    <nav class="navbar navbar-expand-lg navbar-glass py-3 mb-4">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?= base_url('whatsapp') ?>">
                <i class="bi bi-whatsapp text-success fs-3"></i>
                <span class="fw-bold tracking-tight">Gestão de WhatsApp</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-dark border border-secondary text-secondary rounded-pill px-3 py-2 small d-none d-sm-inline-flex align-items-center gap-1">
                    <i class="bi bi-shield-check text-success"></i> Evolution API
                </span>
            </div>
        </div>
    </nav>

    <div class="container">
        
        <!-- Header Info & Plan Widget -->
        <div class="row g-4 mb-4 align-items-stretch">
            <div class="col-lg-6 d-flex flex-column justify-content-center">
                <h2 class="fw-bold text-white mb-2">Conexões WhatsApp</h2>
                <p class="text-secondary mb-0 fs-6">Gerencie instâncias e números WhatsApp conectados ao seu painel.</p>
            </div>
            
            <div class="col-lg-6">
                <div class="glass-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold text-secondary small text-uppercase tracking-wider">Uso do Plano</span>
                        <span class="fw-bold text-white"><?= esc($connectionsCount) ?> / <?= esc($planLimit) ?> conexões</span>
                    </div>
                    <?php 
                        $percent = $planLimit > 0 ? min(100, ($connectionsCount / $planLimit) * 100) : 0;
                        $available = max(0, $planLimit - $connectionsCount);
                    ?>
                    <div class="progress-bar-custom mb-3">
                        <div class="progress-fill <?= $percent >= 100 ? 'bg-danger' : '' ?>" style="width: <?= esc($percent) ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <small class="text-muted d-flex align-items-center gap-1">
                            <i class="bi bi-info-circle text-info"></i>
                            <span class="text-secondary fw-medium"><?= esc($available) ?> disponíveis</span>
                        </small>
                        <?php if ($connectionsCount < $planLimit): ?>
                            <button class="btn btn-primary-gradient d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createConnectionModal">
                                <i class="bi bi-plus-lg"></i> Conectar WhatsApp
                            </button>
                        <?php else: ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2">
                                <i class="bi bi-exclamation-octagon me-1"></i> Limite atingido
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Connections Grid -->
        <div class="row g-4" id="connections-grid">
            <?php if (empty($connections)): ?>
                <div class="col-12">
                    <div class="glass-card text-center py-5 px-4 my-3">
                        <div class="avatar-wrapper mx-auto mb-3" style="width: 70px; height: 70px; font-size: 2rem;">
                            <i class="bi bi-inbox text-secondary fs-2"></i>
                        </div>
                        <h4 class="text-white fw-bold mb-2">Nenhuma conexão encontrada</h4>
                        <p class="text-secondary mb-4 mx-auto" style="max-width: 460px;">Você ainda não cadastrou nenhuma conexão de WhatsApp. Clique no botão abaixo para iniciar.</p>
                        <button class="btn btn-primary-gradient px-4 py-2" data-bs-toggle="modal" data-bs-target="#createConnectionModal">
                            <i class="bi bi-plus-lg me-1"></i> Adicionar Conexão
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($connections as $conn): ?>
                    <?php 
                        $statusClass = 'secondary';
                        $statusText = 'Desconhecido';
                        $statusIcon = 'question-circle';

                        switch ($conn['status']) {
                            case 'connected':
                                $statusClass = 'connected';
                                $statusText = 'Conectado';
                                $statusIcon = 'check-circle-fill';
                                break;
                            case 'waiting_qr':
                                $statusClass = 'waiting_qr';
                                $statusText = 'Aguardando Conexão';
                                $statusIcon = 'qr-code-scan';
                                break;
                            case 'disconnected':
                                $statusClass = 'disconnected';
                                $statusText = 'Desconectado';
                                $statusIcon = 'x-circle-fill';
                                break;
                            case 'configuring':
                            case 'configured':
                                $statusClass = 'configured';
                                $statusText = 'Aguardando ação...';
                                $statusIcon = 'hourglass-split';
                                break;
                        }
                    ?>
                    <div class="col-md-6 col-lg-4 connection-card-wrapper" id="conn-card-<?= esc($conn['id']) ?>">
                        <div class="glass-card card-whatsapp h-100 p-4 d-flex flex-column justify-content-between">
                            <div>
                                <!-- Top Card Header -->
                                <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
                                    <div class="d-flex align-items-center gap-3" style="min-width: 0; flex: 1;">
                                        <div class="avatar-wrapper">
                                            <?php if (!empty($conn['profile_picture'])): ?>
                                                <img src="<?= esc($conn['profile_picture']) ?>" alt="Avatar">
                                            <?php else: ?>
                                                <i class="bi bi-whatsapp"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div style="min-width: 0; flex: 1;">
                                            <h5 class="fw-bold text-white mb-0 text-truncate" title="<?= esc($conn['name'] ?? 'WhatsApp') ?>">
                                                <?= esc($conn['name'] ?? 'WhatsApp') ?>
                                            </h5>
                                            
                                            <!-- Nome da Instância para n8n e integrações -->
                                            <div class="my-1">
                                                <div class="instance-tag-wrapper" title="Nome da Instância para uso no n8n e integrações">
                                                    <span class="instance-tag-label">Instância:</span>
                                                    <span class="instance-tag-value text-truncate" style="max-width: 130px; display: inline-block; vertical-align: middle;" id="inst-name-<?= esc($conn['id']) ?>"><?= esc($conn['instance_name']) ?></span>
                                                    <button type="button" class="btn-copy-instance" onclick="copyInstanceName('<?= esc($conn['instance_name']) ?>', this)" title="Copiar nome da instância">
                                                        <i class="bi bi-copy"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="text-secondary small fw-medium text-truncate" id="phone-<?= esc($conn['id']) ?>">
                                                <?= !empty($conn['phone']) ? '+'.esc($conn['phone']) : '<span class="text-muted">Sem número vinculado</span>' ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dropdown flex-shrink-0">
                                        <button class="btn btn-icon-glass" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Opções">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="checkStatus(<?= esc($conn['id']) ?>)">
                                                    <i class="bi bi-arrow-clockwise me-2 text-info"></i>Atualizar Status
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="showQRModal(<?= esc($conn['id']) ?>)">
                                                    <i class="bi bi-qr-code me-2 text-warning"></i>Conectar / Exibir QR
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="syncProfile(<?= esc($conn['id']) ?>)">
                                                    <i class="bi bi-person-badge me-2 text-primary"></i>Sincronizar Perfil
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="restartInstance(<?= esc($conn['id']) ?>)">
                                                    <i class="bi bi-bootstrap-reboot me-2 text-secondary"></i>Reiniciar Instância
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="#" onclick="disconnectInstance(<?= esc($conn['id']) ?>)">
                                                    <i class="bi bi-plug me-2"></i>Desconectar
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="#" onclick="deleteInstance(<?= esc($conn['id']) ?>)">
                                                    <i class="bi bi-trash me-2"></i>Remover Conexão
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <!-- Status Badge -->
                                <div class="mb-3">
                                    <span class="badge-custom badge-<?= esc($statusClass) ?> w-100" id="badge-<?= esc($conn['id']) ?>" data-status="<?= esc($conn['status']) ?>">
                                        <i class="bi bi-<?= esc($statusIcon) ?>" id="badge-icon-<?= esc($conn['id']) ?>"></i> 
                                        <span id="badge-text-<?= esc($conn['id']) ?>"><?= esc($statusText) ?></span>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Bottom Footer info -->
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top border-secondary border-opacity-10">
                                <span class="text-muted d-flex align-items-center gap-1" style="font-size: 0.78rem;" id="last-conn-<?= esc($conn['id']) ?>">
                                    <i class="bi bi-clock"></i> 
                                    <?= !empty($conn['last_connection']) ? date('d/m/Y H:i', strtotime($conn['last_connection'])) : 'Nunca conectado' ?>
                                </span>
                                <button class="btn btn-sm btn-glass-secondary py-1 px-2" onclick="exportJSON(<?= esc($conn['id']) ?>)" title="Exportar Configuração">
                                    <i class="bi bi-download me-1"></i> Exportar
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Connection Modal -->
    <div class="modal fade" id="createConnectionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-modal">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2">
                        <i class="bi bi-plus-circle text-primary"></i> Conectar Novo WhatsApp
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="text-secondary small mb-4">
                        Defina um nome amigável para identificar esse número ou departamento. A instância técnica na Evolution API será gerada de forma automática.
                    </p>
                    
                    <div class="mb-3">
                        <label for="connection_name" class="form-label">Nome da conexão <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-lg" id="connection_name" placeholder="ex: WhatsApp Atendimento / Comercial" autocomplete="off">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-glass-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary-gradient px-4" onclick="createInstance()">
                        <i class="bi bi-check-lg me-1"></i> Gerar Conexão
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div class="modal fade" id="qrCodeModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-modal">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2">
                        <i class="bi bi-qr-code text-warning"></i> Conectar Aparelho
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="closeQRModal()"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <div id="qr-wrapper" class="py-2 mb-3">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <p class="text-secondary mb-0">Carregando QR Code...</p>
                    </div>
                    
                    <div class="instruction-box text-start">
                        <p class="fw-semibold text-white mb-2 fs-6 d-flex align-items-center gap-2">
                            <i class="bi bi-phone text-success"></i> Siga os passos no seu celular:
                        </p>
                        <ol class="small mb-0 ps-3">
                            <li>Abra o WhatsApp no seu smartphone</li>
                            <li>Acesse <strong>Configurações</strong> ou <strong>Mais opções</strong> (<i class="bi bi-three-dots-vertical"></i>)</li>
                            <li>Selecione <strong>Aparelhos conectados</strong></li>
                            <li>Toque em <strong>Conectar um aparelho</strong> e aponte a câmera para este QR Code</li>
                        </ol>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-glass-secondary w-100" data-bs-dismiss="modal" onclick="closeQRModal()">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.all.min.js"></script>
    <script src="<?= base_url('assets/js/whatsapp.js') ?>"></script>
    
    <script>
        $(document).ready(function() {
            // Start background polling for all connections currently in 'waiting_qr' or 'configuring'
            $('[data-status="waiting_qr"], [data-status="configuring"]').each(function() {
                const id = $(this).attr('id').replace('badge-', '');
                if (id) {
                    startBackgroundPolling(id);
                }
            });
        });
    </script>
</body>
</html>
