<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conexões WhatsApp</title>
    
    <meta name="csrf-token" content="<?= csrf_hash() ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/whatsapp.css') ?>">
    
    <style>
        .progress-bar-custom {
            height: 10px;
            border-radius: 5px;
            background-color: #e9ecef;
            overflow: hidden;
        }
        .progress-bar-custom .progress-fill {
            height: 100%;
            background-color: #6366f1;
            transition: width 0.5s ease;
        }
        .card-whatsapp {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 12px;
        }
        .card-whatsapp:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body class="bg-light">

    <!-- Loader Overlay -->
    <div id="loader-overlay" class="loading-overlay">
        <div class="text-center text-primary">
            <div class="spinner-border mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
            <h5 class="text-light">Processando requisição...</h5>
            <small class="text-muted">Por favor, aguarde.</small>
        </div>
    </div>

    <!-- Header Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-white border-bottom shadow-sm py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 text-dark" href="#">
                <i class="bi bi-whatsapp text-success fs-3"></i>
                <span class="fw-bold tracking-tight">Gestão de WhatsApp</span>
            </a>
        </div>
    </nav>

    <div class="container mt-4 mt-lg-5">
        
        <!-- Header Info -->
        <div class="row mb-4 align-items-center">
            <div class="col-md-6 mb-3 mb-md-0">
                <h3 class="fw-bold mb-1">Conexões WhatsApp</h3>
                <p class="text-muted mb-0">Gerencie os números WhatsApp conectados à sua conta.</p>
            </div>
            <div class="col-md-6">
                <div class="bg-white p-3 rounded-3 shadow-sm border">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold text-secondary">Uso do Plano</span>
                        <span class="fw-bold text-dark"><?= esc($connectionsCount) ?> / <?= esc($planLimit) ?> conexões utilizadas</span>
                    </div>
                    <?php 
                        $percent = $planLimit > 0 ? ($connectionsCount / $planLimit) * 100 : 0;
                        $available = $planLimit - $connectionsCount;
                    ?>
                    <div class="progress-bar-custom mb-2">
                        <div class="progress-fill <?= $percent >= 100 ? 'bg-danger' : '' ?>" style="width: <?= esc($percent) ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted"><?= esc($available) ?> disponíveis</small>
                        <?php if ($connectionsCount < $planLimit): ?>
                            <button class="btn btn-sm btn-primary px-3 rounded-pill" data-bs-toggle="modal" data-bs-target="#createConnectionModal">
                                <i class="bi bi-plus-lg me-1"></i> Conectar WhatsApp
                            </button>
                        <?php else: ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">Limite atingido</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Connections Grid -->
        <div class="row g-4" id="connections-grid">
            <?php if (empty($connections)): ?>
                <div class="col-12 text-center py-5">
                    <div class="text-muted mb-3">
                        <i class="bi bi-inbox fs-1"></i>
                    </div>
                    <h5>Nenhuma conexão encontrada</h5>
                    <p class="text-secondary">Você ainda não conectou nenhum número de WhatsApp.</p>
                    <button class="btn btn-primary px-4 mt-2" data-bs-toggle="modal" data-bs-target="#createConnectionModal">
                        Começar Agora
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($connections as $conn): ?>
                    <?php 
                        $statusClass = 'secondary';
                        $statusText = 'Desconhecido';
                        $statusIcon = 'question-circle';

                        switch ($conn['status']) {
                            case 'connected':
                                $statusClass = 'success';
                                $statusText = 'Conectado';
                                $statusIcon = 'check-circle-fill';
                                break;
                            case 'waiting_qr':
                                $statusClass = 'warning';
                                $statusText = 'Aguardando Conexão';
                                $statusIcon = 'qr-code-scan';
                                break;
                            case 'disconnected':
                                $statusClass = 'danger';
                                $statusText = 'Desconectado';
                                $statusIcon = 'x-circle-fill';
                                break;
                            case 'configuring':
                            case 'configured':
                                $statusClass = 'info';
                                $statusText = 'Aguardando ação...';
                                $statusIcon = 'hourglass-split';
                                break;
                        }
                    ?>
                    <div class="col-md-6 col-lg-4 connection-card-wrapper" id="conn-card-<?= esc($conn['id']) ?>">
                        <div class="card bg-white card-whatsapp h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                                            <?php if (!empty($conn['profile_picture'])): ?>
                                                <img src="<?= esc($conn['profile_picture']) ?>" alt="Avatar" class="rounded-circle w-100 h-100 object-fit-cover">
                                            <?php else: ?>
                                                <i class="bi bi-whatsapp text-success fs-4"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h5 class="fw-bold mb-0 text-truncate" style="max-width:150px;"><?= esc($conn['name'] ?? 'WhatsApp') ?></h5>
                                            <small class="text-muted" id="phone-<?= esc($conn['id']) ?>"><?= !empty($conn['phone']) ? '+'.esc($conn['phone']) : 'Sem número' ?></small>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border-0" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li><a class="dropdown-item" href="#" onclick="checkStatus(<?= esc($conn['id']) ?>)"><i class="bi bi-arrow-clockwise me-2 text-secondary"></i>Atualizar Status</a></li>
                                            
                                            <li><a class="dropdown-item" href="#" onclick="showQRModal(<?= esc($conn['id']) ?>)"><i class="bi bi-qr-code me-2 text-warning"></i>Conectar / Exibir QR</a></li>
                                            
                                            <li><a class="dropdown-item" href="#" onclick="syncProfile(<?= esc($conn['id']) ?>)"><i class="bi bi-person-badge me-2 text-info"></i>Sincronizar Perfil</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="restartInstance(<?= esc($conn['id']) ?>)"><i class="bi bi-bootstrap-reboot me-2 text-primary"></i>Reiniciar Instância</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="disconnectInstance(<?= esc($conn['id']) ?>)"><i class="bi bi-plug me-2 text-danger"></i>Desconectar</a></li>
                                            
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteInstance(<?= esc($conn['id']) ?>)"><i class="bi bi-trash me-2"></i>Remover Conexão</a></li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <span class="badge bg-<?= esc($statusClass) ?>-subtle text-<?= esc($statusClass) ?> border border-<?= esc($statusClass) ?>-subtle rounded-pill px-3 py-2 w-100 d-flex justify-content-center align-items-center gap-2" id="badge-<?= esc($conn['id']) ?>" data-status="<?= esc($conn['status']) ?>">
                                        <i class="bi bi-<?= esc($statusIcon) ?>" id="badge-icon-<?= esc($conn['id']) ?>"></i> 
                                        <span id="badge-text-<?= esc($conn['id']) ?>"><?= esc($statusText) ?></span>
                                    </span>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted" style="font-size: 0.75rem;" id="last-conn-<?= esc($conn['id']) ?>">
                                        <i class="bi bi-clock me-1"></i> 
                                        <?= !empty($conn['last_connection']) ? date('d/m/Y H:i', strtotime($conn['last_connection'])) : 'Nunca conectado' ?>
                                    </small>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="exportJSON(<?= esc($conn['id']) ?>)">
                                        <i class="bi bi-download me-1"></i> Exportar
                                    </button>
                                </div>
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
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>Conectar WhatsApp</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-4">Esse será apenas o nome de identificação da conexão. O nome técnico da instância será gerado automaticamente.</p>
                    
                    <div class="mb-3">
                        <label for="connection_name" class="form-label fw-semibold">Nome da conexão <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-lg bg-light" id="connection_name" placeholder="ex: WhatsApp Comercial">
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary px-4" onclick="createInstance()">Gerar conexão</button>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div class="modal fade" id="qrCodeModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold"><i class="bi bi-qr-code text-dark me-2"></i>Conectar Aparelho</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="closeQRModal()"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <div id="qr-wrapper" class="py-2">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <p class="text-muted">Carregando QR Code...</p>
                    </div>
                    
                    <div class="mt-4 bg-light rounded p-3 text-start">
                        <p class="fw-semibold mb-2 fs-6">Siga os passos no seu celular:</p>
                        <ol class="text-muted small mb-0 ps-3">
                            <li class="mb-1">Abra o WhatsApp no seu celular</li>
                            <li class="mb-1">Toque em <strong>Configurações</strong> ou <strong>Mais opções</strong> <i class="bi bi-three-dots-vertical"></i></li>
                            <li class="mb-1">Toque em <strong>Aparelhos conectados</strong></li>
                            <li>Toque em <strong>Conectar um aparelho</strong> e aponte a câmera para a tela</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.all.min.js"></script>
    <script src="<?= base_url('assets/js/whatsapp.js') ?>"></script>
    
    <script>
        $(document).ready(function() {
            // Start background polling for all connections currently in 'waiting_qr'
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
