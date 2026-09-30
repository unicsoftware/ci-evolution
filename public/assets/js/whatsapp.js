/**
 * Evolution API WhatsApp Integration JS
 * Multi-tenant / Multi-card Version
 */

let activePollingIntervals = {};
let activeQRCountdowns = {};
const POLLING_INTERVAL_MS = 5000;

$(document).ready(function () {
    $.ajaxSetup({
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken()
        }
    });

    $(document).ajaxComplete(function (event, xhr) {
        try {
            const res = xhr.responseJSON;
            if (res && res.csrf_token) {
                updateCsrfToken(res.csrf_token);
            }
        } catch (e) { }
    });
});

function getCsrfToken() {
    return $('meta[name="csrf-token"]').attr('content') || '';
}

function updateCsrfToken(newToken) {
    if (newToken) {
        $('meta[name="csrf-token"]').attr('content', newToken);
        $.ajaxSetup({
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': newToken
            }
        });
    }
}

function toggleLoader(show = true) {
    if (show) {
        $('#loader-overlay').addClass('active');
    } else {
        $('#loader-overlay').removeClass('active');
    }
}

/**
 * Creates a new WhatsApp instance.
 */
function createInstance() {
    const name = $('#connection_name').val().trim();

    if (!name) {
        Swal.fire({
            icon: 'warning',
            title: 'Campo obrigatório',
            text: 'Por favor, informe o nome da conexão.',
            confirmButtonColor: '#6366f1'
        });
        return;
    }

    toggleLoader(true);
    $('#createConnectionModal').modal('hide');

    $.ajax({
        url: '/whatsapp/create',
        type: 'POST',
        data: { name: name },
        dataType: 'json',
        success: function (res) {
            toggleLoader(false);
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Instância Criada!',
                    text: res.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: res.message,
                    confirmButtonColor: '#6366f1'
                });
            }
        },
        error: function (xhr) {
            toggleLoader(false);
            const err = xhr.responseJSON;
            Swal.fire({
                icon: 'error',
                title: 'Erro de validação',
                text: err ? err.message : 'Falha ao processar criação de instância.',
                confirmButtonColor: '#6366f1'
            });
        }
    });
}

/**
 * Checks the status of a specific connection.
 */
function checkStatus(id, isBackground = false) {
    if (!isBackground) toggleLoader(true);

    $.ajax({
        url: '/whatsapp/status/' + id,
        type: 'GET',
        dataType: 'json',
        success: function (res) {
            if (!isBackground) toggleLoader(false);

            if (res.success && res.data) {
                const connected = (res.data.connected === true || res.data.status === 'connected' || res.data.api_state === 'open');
                const status = connected ? 'connected' : res.data.status;
                const config = res.data.config;

                updateCardUI(id, status, config);

                if (connected) {
                    stopPolling(id);
                    const isModalOpen = $('#qrCodeModal').hasClass('show') || $('#qrCodeModal').is(':visible');
                    const modalActiveId = $('#qrCodeModal').data('active-id');

                    if (isModalOpen) {
                        closeQRModal();
                        $('#qrCodeModal').modal('hide');

                        Swal.fire({
                            icon: 'success',
                            title: 'WhatsApp Conectado!',
                            text: 'Aparelho conectado com sucesso com a Evolution API.',
                            confirmButtonColor: '#6366f1',
                            confirmButtonText: 'Excelente!',
                            timer: 3500,
                            timerProgressBar: true
                        }).then(() => {
                            location.reload();
                        });
                    } else if (!isBackground) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Conectado!',
                            text: 'Status verificado. O WhatsApp está conectado.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                } else if (status === 'waiting_qr') {
                    if (res.data.qr_base64 && ($('#qrCodeModal').hasClass('show') || $('#qrCodeModal').is(':visible')) && $('#qrCodeModal').data('active-id') == id) {
                        renderQRCode(id, res.data.qr_base64);
                    }
                }
            }
        },
        error: function (xhr) {
            if (!isBackground) {
                toggleLoader(false);
                const err = xhr.responseJSON;
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: err ? err.message : 'Erro ao consultar status da conexão.',
                    confirmButtonColor: '#6366f1'
                });
            }
        }
    });
}

/**
 * Polls status in the background for a specific connection.
 */
function startBackgroundPolling(id, intervalMs = POLLING_INTERVAL_MS) {
    if (activePollingIntervals[id]) clearInterval(activePollingIntervals[id]);
    
    console.log("Polling started for connection " + id + " at " + intervalMs + "ms");
    activePollingIntervals[id] = setInterval(function () {
        checkStatus(id, true);
    }, intervalMs);
}

function stopPolling(id = null) {
    if (id && activePollingIntervals[id]) {
        clearInterval(activePollingIntervals[id]);
        delete activePollingIntervals[id];
        console.log("Polling stopped for connection " + id);
    }
}

/**
 * Updates the UI of a specific connection card without reloading.
 */
function updateCardUI(id, status, config) {
    const badge = $('#badge-' + id);
    const badgeIcon = $('#badge-icon-' + id);
    const badgeText = $('#badge-text-' + id);
    
    // Reset all status and badge classes
    badge.attr('class', 'badge-custom w-100');

    let badgeStatusClass = 'badge-' + status;
    let text = 'Desconhecido';
    let icon = 'question-circle';

    switch (status) {
        case 'connected':
            text = 'Conectado';
            icon = 'check-circle-fill';
            break;
        case 'waiting_qr':
            text = 'Aguardando Conexão';
            icon = 'qr-code-scan';
            break;
        case 'disconnected':
            text = 'Desconectado';
            icon = 'x-circle-fill';
            break;
        case 'configuring':
        case 'configured':
            badgeStatusClass = 'badge-configured';
            text = 'Aguardando ação...';
            icon = 'hourglass-split';
            break;
        default:
            badgeStatusClass = 'badge-not_configured';
            break;
    }

    badge.addClass(badgeStatusClass);
    badgeIcon.attr('class', `bi bi-${icon}`);
    badgeText.text(text);
    badge.attr('data-status', status);

    if (config) {
        if (config.phone) $('#phone-' + id).text('+' + config.phone);
    }
}

/**
 * QR Code Modal Handlers
 */
function showQRModal(id) {
    $('#qrCodeModal').data('active-id', id);
    $('#qr-wrapper').html(`
        <div class="spinner-border text-primary mb-3" role="status"></div>
        <p class="text-secondary mb-0">Obtendo QR Code...</p>
    `);
    $('#qrCodeModal').modal('show');
    loadQRCode(id);
    startBackgroundPolling(id, 2500);
}

function closeQRModal() {
    const activeId = $('#qrCodeModal').data('active-id');
    if (activeId && activeQRCountdowns[activeId]) {
        clearInterval(activeQRCountdowns[activeId]);
    }
    // We don't stop the background polling entirely so the card can still update if the user connects
}

function loadQRCode(id) {
    if (activeQRCountdowns[id]) clearInterval(activeQRCountdowns[id]);

    $.ajax({
        url: '/whatsapp/qrcode/' + id,
        type: 'GET',
        dataType: 'json',
        success: function (res) {
            if (res.success && res.data && res.data.base64) {
                renderQRCode(id, res.data.base64);
            } else if (res.success && res.data && res.data.code) {
                renderQRCode(id, res.data.code);
            } else {
                $('#qr-wrapper').html(`
                    <div class="alert alert-warning m-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>Não foi possível carregar o QR Code.
                        <button class="btn btn-sm btn-outline-dark mt-2 d-block mx-auto" onclick="loadQRCode(${id})">Tentar Novamente</button>
                    </div>
                `);
            }
        },
        error: function (xhr) {
            $('#qr-wrapper').html(`
                <div class="alert alert-danger m-0">
                    <i class="bi bi-x-circle me-2"></i>Erro ao gerar QR Code.
                    <button class="btn btn-sm btn-outline-danger mt-2 d-block mx-auto" onclick="loadQRCode(${id})">Tentar Novamente</button>
                </div>
            `);
        }
    });
}

function renderQRCode(id, qrSrc) {
    if (!qrSrc.startsWith('data:')) {
        qrSrc = 'data:image/png;base64,' + qrSrc;
    }
    
    $('#qr-wrapper').html(`
        <div class="qr-container position-relative d-inline-block">
            <img src="${qrSrc}" alt="WhatsApp QR Code" class="img-fluid shadow-sm rounded" style="max-height: 250px;">
            <div id="qr-countdown-badge-${id}" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="z-index: 10;">
                30s
            </div>
        </div>
    `);
    
    startQRRefreshCountdown(id, 30);
}

function startQRRefreshCountdown(id, seconds) {
    if (activeQRCountdowns[id]) clearInterval(activeQRCountdowns[id]);
    
    let remaining = seconds;
    const badge = $('#qr-countdown-badge-' + id);
    
    activeQRCountdowns[id] = setInterval(() => {
        remaining--;
        if (remaining <= 0) {
            clearInterval(activeQRCountdowns[id]);
            badge.text('0s');
            badge.removeClass('bg-danger').addClass('bg-secondary');
            loadQRCode(id); // reload
        } else {
            badge.text(remaining + 's');
            if (remaining <= 5) {
                badge.addClass('opacity-75'); // simple pulse
            }
        }
    }, 1000);
}

/**
 * Instance Operations
 */
function disconnectInstance(id) {
    Swal.fire({
        title: 'Desconectar WhatsApp?',
        text: 'Você precisará ler o QR Code novamente para se reconectar.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sim, desconectar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            toggleLoader(true);
            $.ajax({
                url: '/whatsapp/logout/' + id,
                type: 'POST',
                dataType: 'json',
                success: function (res) {
                    toggleLoader(false);
                    if (res.success) {
                        Swal.fire('Desconectado', 'Sessão encerrada com sucesso.', 'success').then(() => location.reload());
                    }
                },
                error: function (xhr) {
                    toggleLoader(false);
                    const err = xhr.responseJSON;
                    Swal.fire('Erro', err ? err.message : 'Falha ao encerrar a sessão.', 'error');
                }
            });
        }
    });
}

function restartInstance(id) {
    toggleLoader(true);
    $.ajax({
        url: '/whatsapp/restart/' + id,
        type: 'POST',
        dataType: 'json',
        success: function (res) {
            toggleLoader(false);
            if (res.success) {
                Swal.fire('Reiniciando', 'A instância está reiniciando.', 'info').then(() => {
                    checkStatus(id);
                });
            }
        },
        error: function (xhr) {
            toggleLoader(false);
            const err = xhr.responseJSON;
            Swal.fire('Erro', err ? err.message : 'Falha ao reiniciar.', 'error');
        }
    });
}

function deleteInstance(id) {
    Swal.fire({
        title: 'Remover Conexão?',
        text: 'Isso apagará todas as configurações locais e removerá a instância permanentemente.',
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sim, excluir!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            toggleLoader(true);
            $.ajax({
                url: '/whatsapp/delete/' + id,
                type: 'DELETE',
                dataType: 'json',
                success: function (res) {
                    toggleLoader(false);
                    if (res.success) {
                        Swal.fire('Removido', res.message, 'success').then(() => location.reload());
                    }
                },
                error: function (xhr) {
                    toggleLoader(false);
                    const err = xhr.responseJSON;
                    Swal.fire('Erro', err ? err.message : 'Falha ao remover a conexão.', 'error');
                }
            });
        }
    });
}

function syncProfile(id) {
    toggleLoader(true);
    $.ajax({
        url: '/whatsapp/profile/' + id,
        type: 'POST',
        dataType: 'json',
        success: function (res) {
            toggleLoader(false);
            if (res.success && res.data) {
                Swal.fire('Sincronizado', 'O perfil foi atualizado com sucesso.', 'success').then(() => location.reload());
            }
        },
        error: function (xhr) {
            toggleLoader(false);
            Swal.fire('Erro', 'Falha ao sincronizar o perfil.', 'error');
        }
    });
}

function exportJSON(id) {
    // Triggers download of the config JSON
    window.location.href = '/whatsapp/export/' + id;
}

/**
 * Copies text to clipboard and gives visual feedback
 */
function copyInstanceName(instanceName, btnElement) {
    if (!instanceName) return;

    navigator.clipboard.writeText(instanceName).then(() => {
        const icon = $(btnElement).find('i');
        const originalClass = icon.attr('class');
        
        icon.attr('class', 'bi bi-check2 text-success');
        
        // Optional quick toast
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true,
            background: '#131c2e',
            color: '#ffffff'
        });
        
        Toast.fire({
            icon: 'success',
            title: `Instância copiada: ${instanceName}`
        });

        setTimeout(() => {
            icon.attr('class', originalClass);
        }, 2000);
    }).catch(err => {
        console.error('Falha ao copiar:', err);
    });
}
