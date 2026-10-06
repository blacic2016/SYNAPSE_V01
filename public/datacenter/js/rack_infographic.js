/**
 * Rack Infographic Renderer & Image Generator
 * CMDB VILASECA / DCIM
 * Supports Frontal, Posterior (Rear), and Dual (Side-by-Side) perspectives
 */

let currentRackData = null;
let currentViewSide = 'front'; // 'front' | 'rear' | 'dual'

/**
 * Open Rack Infographic View Modal
 */
function openRackView(rackId) {
    if (!rackId) return;

    // Show loading state in modal
    $('#rackViewModal').modal('show');
    $('#rack-infographic-loading').show();
    $('#rack-infographic-card').hide();
    $('#rack-infographic-error').hide();

    $.ajax({
        url: 'api.php',
        method: 'GET',
        data: {
            action: 'get_rack_infographic',
            rack_id: rackId
        },
        dataType: 'json',
        success: function(response) {
            $('#rack-infographic-loading').hide();
            if (response.success && response.rack) {
                currentRackData = response;
                // Preserve current view mode or default to front
                if (!['front', 'rear', 'dual'].includes(currentViewSide)) {
                    currentViewSide = 'front';
                }
                setRackViewSide(currentViewSide, false);
                renderRackInfographic(response);
                $('#rack-infographic-card').fadeIn(200);
            } else {
                showInfographicError(response.message || 'No se pudo cargar la información del bastidor.');
            }
        },
        error: function(xhr, status, error) {
            $('#rack-infographic-loading').hide();
            showInfographicError('Error de comunicación con el servidor: ' + error);
        }
    });
}

function showInfographicError(msg) {
    $('#rack-infographic-error-text').text(msg);
    $('#rack-infographic-error').show();
}

/**
 * Set and switch view mode: 'front' | 'rear' | 'dual'
 */
function setRackViewSide(side, triggerRender = true) {
    if (!['front', 'rear', 'dual'].includes(side)) side = 'front';
    currentViewSide = side;

    // 1. Update toolbar buttons active state
    $('.view-side-btn').removeClass('active btn-light').addClass('btn-outline-light');
    if (side === 'front') $('#btnSideFront').removeClass('btn-outline-light').addClass('btn-light active');
    else if (side === 'rear') $('#btnSideRear').removeClass('btn-outline-light').addClass('btn-light active');
    else if (side === 'dual') $('#btnSideDual').removeClass('btn-outline-light').addClass('btn-light active');

    // 2. Update header segmented pills active state
    $('.info-view-switch .info-pill-btn').removeClass('active');
    if (side === 'front') $('#pill-view-front').addClass('active');
    else if (side === 'rear') $('#pill-view-rear').addClass('active');
    else if (side === 'dual') $('#pill-view-dual').addClass('active');

    // 3. Update DOM container visibility and layout classes
    const grid = $('#info-body-grid-container');
    const card = $('#rack-infographic-card');
    const colFront = $('#col-cabinet-front');
    const colRear = $('#col-cabinet-rear');

    if (side === 'front') {
        grid.removeClass('dual-view');
        card.removeClass('dual-view-card');
        colFront.show();
        colRear.hide();
    } else if (side === 'rear') {
        grid.removeClass('dual-view');
        card.removeClass('dual-view-card');
        colFront.hide();
        colRear.show();
    } else if (side === 'dual') {
        grid.addClass('dual-view');
        card.addClass('dual-view-card');
        colFront.show();
        colRear.show();
    }

    // Update footer label
    const sideLabel = (side === 'front') ? 'Vista frontal' : ((side === 'rear') ? 'Vista posterior (trasera)' : 'Vista dual (Frontal + Posterior)');
    const urDirLabel = (currentRackData && currentRackData.rack && currentRackData.rack.numbering_dir === 'DOWN') ? 'UR 1 abajo' : 'UR 1 arriba';
    const today = new Date();
    const formattedDate = `${String(today.getDate()).padStart(2, '0')}/${String(today.getMonth() + 1).padStart(2, '0')}/${today.getFullYear()}`;
    $('#info-footer-text').text(`Fuente: CMDB VILASECA / DCIM • * datos tomados del inventario físico y fotos de campo • ${sideLabel} • ${urDirLabel} • Generado el ${formattedDate}`);

    if (triggerRender && currentRackData) {
        renderRackInfographic(currentRackData);
    }
}

/**
 * Toggle side cyclically (Front -> Rear -> Dual -> Front)
 */
function toggleRackViewSide() {
    if (currentViewSide === 'front') setRackViewSide('rear');
    else if (currentViewSide === 'rear') setRackViewSide('dual');
    else setRackViewSide('front');
}

/**
 * Render the entire Infographic layout
 */
function renderRackInfographic(data) {
    const rack = data.rack;
    const stats = data.stats;
    const devices = data.devices || [];

    // 1. Header Information
    const siteTitle = rack.location || rack.city || 'DATACENTER';
    $('#info-site-title').text(siteTitle);
    $('#info-diagram-subtitle').text(`DIAGRAMA DE RACK · ${rack.name} · ${rack.location || rack.city}`);
    $('#info-client-name').text(rack.client || 'GRUPO VILASECA');

    // Badges
    $('#info-badge-total-u').text(`${rack.total_u}U`);
    const mountingType = (parseInt(rack.total_u) <= 20) ? 'AÉREO' : 'GABINETE DE PISO';
    $('#info-badge-type').text(mountingType);

    // 2. Summary KPI Cards & Occupancy Bar
    $('#kpi-total-u').text(stats.total_u);
    $('#kpi-occupied-u').text(stats.occupied_u);
    $('#kpi-free-u').text(stats.free_u);
    $('#info-occupancy-pct').text(`${stats.occupancy_pct} %`);
    $('#info-occupancy-bar').css('width', `${stats.occupancy_pct}%`);

    // 3. Cabinet Header & Footer Plates
    $('#cabinet-header-text').html(`${escapeHtml(rack.name)} · ${escapeHtml(rack.location || rack.city)} <span class="badge badge-primary" style="font-size:8px;">FRONTAL</span>`);
    $('#cabinet-footer-text').text(`${rack.total_u}U · ${mountingType} · VISTA FRONTAL`);

    $('#cabinet-rear-header-text').html(`${escapeHtml(rack.name)} · ${escapeHtml(rack.location || rack.city)} <span class="badge badge-info" style="font-size:8px;">POSTERIOR</span>`);
    $('#cabinet-rear-footer-text').text(`${rack.total_u}U · ACCESO TRASERO`);

    // 4. Render BOTH Cabinets simultaneously so switching between front/rear/dual is instantaneous and reliable
    renderCabinetUnits(rack, devices, 'front', '#cabinet-rail-left', '#cabinet-slots-col', '#cabinet-rail-right', '#cabinet-pdu-col-left', '#cabinet-pdu-col-right');
    renderCabinetUnits(rack, devices, 'rear', '#cabinet-rear-rail-left', '#cabinet-rear-slots-col', '#cabinet-rear-rail-right', '#cabinet-rear-pdu-col-left', '#cabinet-rear-pdu-col-right');

    // 5. Render Detalle por Unidad (UR) Table
    renderUnitsDetailTable(rack, devices);

    // 6. Observations
    renderObservations(rack.observations || []);

    // 7. Evidence Photos
    renderEvidencePhotos(rack.photos || []);

    // 8. Footer Date
    const today = new Date();
    const formattedDate = `${String(today.getDate()).padStart(2, '0')}/${String(today.getMonth() + 1).padStart(2, '0')}/${today.getFullYear()}`;
    const sideLabel = (currentViewSide === 'front') ? 'Vista frontal' : ((currentViewSide === 'rear') ? 'Vista posterior (trasera)' : 'Vista dual (Frontal + Posterior)');
    const urDirLabel = (rack.numbering_dir === 'DOWN') ? 'UR 1 abajo' : 'UR 1 arriba';
    $('#info-footer-text').text(`Fuente: CMDB VILASECA / DCIM • * datos tomados del inventario físico y fotos de campo • ${sideLabel} • ${urDirLabel} • Generado el ${formattedDate}`);
}

/**
 * Determine device visual category
 */
function classifyDevice(dev) {
    const name = (dev.name || '').toLowerCase();
    const details = dev.details || {};
    const model = (details.model || '').toLowerCase();
    const make = (details.make || '').toLowerCase();
    const devType = (details.type || '').toLowerCase();

    if (devType === 'patch_panel' || name.includes('patch panel') || name.includes('patchpanel') || name.includes('pp-') || name.includes('ppa') || name.includes('ppc')) {
        return { category: 'patch_panel', label: 'Patch panel', color: '#334155' };
    }
    if (devType === 'organizer' || name.includes('organizador') || name.includes('ordenador') || name.includes('cable duct')) {
        return { category: 'organizer', label: 'Organizador de cable', color: '#475569' };
    }
    if (devType === 'switch' || name.includes('switch') || name.includes('sw-') || name.includes('sw ') || model.includes('switch') || model.includes('catalyst')) {
        return { category: 'switch', label: 'Switch', color: '#0284c7' };
    }
    if (devType === 'firewall' || name.includes('firewall') || name.includes('fortinet') || name.includes('fortigate') || name.includes('fw-') || model.includes('fortigate')) {
        return { category: 'firewall', label: 'Firewall', color: '#dc2626' };
    }
    if (devType === 'router' || name.includes('router') || name.includes('cisco 881') || name.includes('rt-') || name.includes('rt0') || model.includes('881')) {
        return { category: 'router', label: 'Router', color: '#1e40af' };
    }
    if (devType === 'shelf' || name.includes('bandeja') || name.includes('shelf') || name.includes('tray')) {
        return { category: 'shelf', label: 'Bandeja', color: '#94a3b8' };
    }
    if (devType === 'nvr' || name.includes('nvr') || name.includes('dvr') || name.includes('hikvision') || name.includes('grabador')) {
        return { category: 'nvr', label: 'NVR', color: '#7c3aed' };
    }
    if (devType === 'pdu' || name.includes('pdu') || name.includes('multitoma') || name.includes('regleta') || name.includes('power distribution')) {
        return { category: 'pdu', label: 'PDU', color: '#d97706' };
    }
    if (devType === 'ap' || name.includes('access point') || name.includes('ap-') || name.includes('rap') || name.includes('rg-rap') || name.includes('wifi') || name.includes('wireless')) {
        return { category: 'ap', label: 'Access point', color: '#16a34a' };
    }
    if (devType === 'ups' || name.includes('ups') || name.includes('no-break') || name.includes('bateria') || name.includes('smart-ups')) {
        return { category: 'ups', label: 'UPS', color: '#1e293b' };
    }
    if (devType === 'server' || name.includes('server') || name.includes('servidor') || name.includes('proliant') || name.includes('poweredge')) {
        return { category: 'server', label: 'Servidor', color: '#4338ca' };
    }

    return { category: 'other', label: 'Equipo', color: '#64748b' };
}

/**
 * Render Cabinet Units for a given side ('front' or 'rear')
 */
function renderCabinetUnits(rack, devices, side, railLeftSel, slotsColSel, railRightSel, pduLeftSel, pduRightSel) {
    const totalU = parseInt(rack.total_u) || 19;
    const numberingDir = rack.numbering_dir || 'UP';

    const railLeft = $(railLeftSel);
    const railRight = $(railRightSel);
    const slotsCol = $(slotsColSel);
    const pduLeft = pduLeftSel ? $(pduLeftSel) : null;
    const pduRight = pduRightSel ? $(pduRightSel) : null;

    railLeft.empty();
    railRight.empty();
    slotsCol.empty();
    if (pduLeft) pduLeft.empty().hide();
    if (pduRight) pduRight.empty().hide();

    // Map units: U1 to totalU
    const uOrder = [];
    if (numberingDir === 'UP') {
        for (let u = 1; u <= totalU; u++) uOrder.push(u);
    } else {
        for (let u = totalU; u >= 1; u--) uOrder.push(u);
    }

    // Index devices by start_u (Horizontal) and identify vertical PDUs
    const devByStartU = {};
    const occupiedUnits = {};
    let verticalPduLeft = null;
    let verticalPduRight = null;

    devices.forEach(dev => {
        const devSide = dev.orientation || 'front';
        const depth = (dev.details && dev.details.depth) || 'full';

        // Check if device is visible on this side
        let isVisible = false;
        if (depth === 'full') {
            isVisible = true;
        } else {
            if (side === 'front' && (devSide === 'front' || devSide === 'both')) isVisible = true;
            if (side === 'rear' && (devSide === 'rear' || devSide === 'both')) isVisible = true;
        }
        if (!isVisible) return;

        // Detectar si es PDU Vertical (0U Lateral)
        const isVert = (dev.details && (dev.details.mounting === 'vertical_left' || dev.details.mounting === 'vertical_right' || dev.details.is_vertical));
        if (isVert) {
            if (dev.details && dev.details.mounting === 'vertical_right') {
                verticalPduRight = dev;
            } else {
                verticalPduLeft = dev;
            }
            return; // No colocar en rejilla de slots horizontales
        }

        const startU = parseInt(dev.start_u);
        const hU = Math.max(1, parseInt(dev.height_u) || 1);

        // En el DOM, las ranuras se renderizan de arriba hacia abajo.
        // Si numberingDir === 'UP': U1 está arriba, por lo que el ancla superior es startU.
        // Si numberingDir === 'DOWN': U42 está arriba y U1 abajo, por lo que el ancla superior en pantalla es (startU + hU - 1).
        const anchorU = (numberingDir === 'DOWN') ? (startU + hU - 1) : startU;
        devByStartU[anchorU] = dev;

        for (let u = startU; u < startU + hU; u++) {
            occupiedUnits[u] = dev;
        }
    });

    // Renderizar Canales de PDUs Verticales si existen en esta cara
    if (verticalPduLeft && pduLeft) {
        pduLeft.show().html(createVerticalPduFaceplate(verticalPduLeft, totalU));
    }
    if (verticalPduRight && pduRight) {
        pduRight.show().html(createVerticalPduFaceplate(verticalPduRight, totalU));
    }

    // Dynamic slot height (más grande y legible para apreciar detalles y ambas PDUs con claridad)
    let slotHeight = 38;
    if (totalU > 36) slotHeight = 30;
    else if (totalU > 24) slotHeight = 34;

    uOrder.forEach(u => {
        // Rail items (Los números de UR siempre en el exterior, 100% visibles y claros)
        const railSlotL = $(`<div class="rail-unit-slot" style="height: ${slotHeight}px;">${u}<span class="rack-bolt"></span></div>`);
        const railSlotR = $(`<div class="rail-unit-slot" style="height: ${slotHeight}px;">${u}<span class="rack-bolt"></span></div>`);
        railLeft.append(railSlotL);
        railRight.append(railSlotR);

        // Slot item
        const slotDiv = $(`<div class="rack-u-slot" style="height: ${slotHeight}px;"></div>`);

        if (devByStartU[u]) {
            const dev = devByStartU[u];
            const hU = Math.max(1, parseInt(dev.height_u) || 1);
            const devHeight = hU * slotHeight;
            const faceplate = (side === 'front') ? createDeviceFrontFaceplate(dev, devHeight) : createDeviceRearFaceplate(dev, devHeight);
            slotDiv.append(faceplate);
        } else if (!occupiedUnits[u]) {
            slotDiv.addClass('empty-slot');
            slotDiv.text(side === 'front' ? 'VACÍO · DISPONIBLE' : 'VACÍO [ACCESO TRASERO]');
        }

        slotsCol.append(slotDiv);
    });
}

/**
 * Build realistic VERTICAL PDU faceplate for the lateral channel
 */
function createVerticalPduFaceplate(dev, totalU) {
    const name = dev.name || 'PDU Vertical';
    const details = dev.details || {};
    const model = details.model || '0U Lateral';
    
    // Generar tomas de energía según altura
    const outletCount = Math.min(22, Math.max(6, Math.floor(totalU * 0.4)));
    let outletsHtml = '';
    for (let i = 0; i < outletCount; i++) {
        outletsHtml += '<div class="info-pdu-outlet" title="Toma de Corriente C13/C19"></div>';
    }

    return `
        <div class="info-vertical-pdu" title="${escapeHtml(name)} [${escapeHtml(model)}]">
            <div class="info-pdu-header-meter" title="Línea de Potencia / Voltaje">
                <i class="fas fa-bolt text-warning" style="font-size:7px;"></i> 220V
            </div>
            <div class="info-pdu-outlets-col">
                ${outletsHtml}
            </div>
            <div class="info-pdu-label-vertical" title="${escapeHtml(name)}">
                <i class="fas fa-plug text-info mr-1" style="transform: rotate(90deg);"></i> ${escapeHtml(name)}
            </div>
        </div>
    `;
}

/**
 * Build realistic FRONT faceplate for a device
 */
function createDeviceFrontFaceplate(dev, heightPx) {
    const classification = classifyDevice(dev);
    const cat = classification.category;
    const name = dev.name || 'Dispositivo';
    const details = dev.details || {};
    const serial = details.serial_number || '';
    const func = (details.server_function || '').toUpperCase();

    const hStyle = heightPx ? `height: ${heightPx}px;` : 'height: 100%;';
    const face = $(`<div class="appliance-face" data-device-id="${dev.id || ''}" style="${hStyle}" title="${escapeHtml(name)} (${dev.height_u || 1}U)"></div>`);

    switch (cat) {
        case 'patch_panel':
            face.addClass('face-patch-panel');
            face.html(`
                <div class="pp-label-box">
                    <span class="pp-title">${escapeHtml(name)}</span>
                    <span class="pp-tag">${escapeHtml(details.make || 'CAT6')} · 24P</span>
                </div>
                <div class="pp-ports-matrix">
                    ${[1, 2, 3, 4].map(g => `
                        <div class="pp-port-group">
                            ${[1, 2, 3, 4, 5, 6].map(() => `<div class="pp-rj45"></div>`).join('')}
                        </div>
                    `).join('')}
                </div>
            `);
            break;

        case 'organizer':
            face.addClass('face-organizer');
            face.html(`
                <span class="organizer-label">${escapeHtml(name)}</span>
                <div class="organizer-comb"></div>
            `);
            break;

        case 'switch':
            face.addClass('face-switch');
            face.html(`
                <div class="sw-info">
                    <span class="sw-model">${escapeHtml(name)}</span>
                    ${serial ? `<span class="sw-sn">S/N: ${escapeHtml(serial)}</span>` : ''}
                </div>
                <div class="sw-ports-block">
                    <div class="sw-port-grid">
                        ${[1, 2, 3, 4, 5, 6, 7, 8].map(() => `
                            <div class="sw-port-item"><span class="sw-led"></span></div>
                        `).join('')}
                    </div>
                    <div class="sw-port-grid">
                        ${[1, 2, 3, 4, 5, 6, 7, 8].map(() => `
                            <div class="sw-port-item"><span class="sw-led"></span></div>
                        `).join('')}
                    </div>
                    <div class="sw-sfp">
                        <div class="sw-sfp-cage" title="SFP 1"></div>
                        <div class="sw-sfp-cage" title="SFP 2"></div>
                    </div>
                </div>
            `);
            break;

        case 'firewall':
            face.addClass('face-firewall');
            const roleClass = func.includes('ESCLAVO') ? 'esclavo' : 'principal';
            const roleText = func.includes('ESCLAVO') ? 'ESCLAVO' : (func.includes('PRINCIPAL') ? 'PRINCIPAL' : 'FIREWALL');
            face.html(`
                <div class="fw-info">
                    <span class="fw-title">
                        ${escapeHtml(name)}
                        <span class="fw-role-tag ${roleClass}">${roleText}</span>
                    </span>
                    ${serial ? `<span class="fw-sn">S/N: ${escapeHtml(serial)}</span>` : ''}
                </div>
                <div class="fw-ports">
                    <div class="fw-port-slot" title="CONSOLE"></div>
                    <div class="fw-port-slot" title="WAN 1"></div>
                    <div class="fw-port-slot" title="WAN 2"></div>
                    <div class="fw-port-slot" title="PORT 1"></div>
                </div>
            `);
            break;

        case 'router':
            face.addClass('face-router');
            const rtBadge = func.includes('BACKUP') ? 'BACKUP' : 'PRINCIPAL';
            face.html(`
                <div style="display:flex; align-items:center;">
                    <div class="rt-cisco-logo">
                        <span class="rt-cisco-bar" style="height:6px;"></span>
                        <span class="rt-cisco-bar" style="height:10px;"></span>
                        <span class="rt-cisco-bar" style="height:12px;"></span>
                        <span class="rt-cisco-bar" style="height:10px;"></span>
                        <span class="rt-cisco-bar" style="height:6px;"></span>
                    </div>
                    <div class="rt-title-box">
                        <span class="rt-name">${escapeHtml(name)}</span>
                        <span class="rt-badge">${rtBadge}</span>
                    </div>
                </div>
                <div class="rt-ports">
                    <div class="rt-port-cons" title="Console"></div>
                    <div class="rt-port-lan" title="FastEthernet 0"></div>
                    <div class="rt-port-lan" title="FastEthernet 1"></div>
                </div>
            `);
            break;

        case 'shelf':
            face.addClass('face-shelf');
            face.html(`
                <span class="shelf-label">${escapeHtml(name)}</span>
                <div class="shelf-perf"></div>
            `);
            break;

        case 'nvr':
            face.addClass('face-nvr');
            face.html(`
                <span class="nvr-logo">${escapeHtml(name)}</span>
                <div class="nvr-leds">
                    <span class="nvr-led green" title="Power"></span>
                    <span class="nvr-led blue" title="Network"></span>
                    <span class="nvr-led red" title="Record"></span>
                </div>
            `);
            break;

        case 'pdu':
            face.addClass('face-pdu');
            face.html(`
                <span class="pdu-label">${escapeHtml(name)}</span>
                <div class="pdu-outlets">
                    <div class="pdu-nema"></div>
                    <div class="pdu-nema"></div>
                    <div class="pdu-nema"></div>
                    <div class="pdu-nema"></div>
                    <div class="pdu-nema"></div>
                    <div class="pdu-nema"></div>
                </div>
                <div class="pdu-switch" title="Power Switch"></div>
            `);
            break;

        case 'ap':
            face.addClass('face-ap');
            face.html(`
                <div class="ap-capsule">
                    <span class="ap-name">${escapeHtml(name)}</span>
                    <span class="ap-led-ring" title="WiFi Status"></span>
                </div>
            `);
            break;

        case 'ups':
            face.addClass('face-ups');
            face.html(`
                <div class="ups-row">
                    <div>
                        <div class="ups-label">${escapeHtml(name)}</div>
                        <div class="ups-sub">${escapeHtml(details.model || 'SMART-UPS')}</div>
                    </div>
                    <div class="ups-apc-logo">APC</div>
                    <div>
                        <span class="ups-led" title="En Línea"></span>
                    </div>
                </div>
            `);
            break;

        case 'server':
            face.addClass('face-server');
            face.html(`
                <div class="sw-info">
                    <span class="sw-model" style="color:#ffffff;">${escapeHtml(name)}</span>
                    <span class="sw-sn" style="color:#ffffff;">${escapeHtml(details.model || 'POWEREDGE / PROLIANT')}</span>
                </div>
                <div class="srv-drive-bays">
                    ${[1, 2, 3, 4, 5, 6].map(i => `
                        <div class="srv-drive-caddy" title="Disco SAS/SATA ${i}">
                            <span class="srv-caddy-led"></span>
                        </div>
                    `).join('')}
                </div>
                <div class="srv-ctrl-panel">
                    <span class="srv-pwr-btn" title="Power On/Off"></span>
                    <span class="srv-id-btn" title="System ID LED"></span>
                </div>
            `);
            break;

        default:
            face.css('background', details.color || '#1e293b');
            face.html(`
                <div style="display:flex; justify-content:space-between; width:100%; align-items:center;">
                    <span style="font-size:8px; font-weight:800; color:#ffffff;">${escapeHtml(name)}</span>
                    ${serial ? `<span style="font-size:7px; color:#ffffff;">S/N: ${escapeHtml(serial)}</span>` : ''}
                </div>
            `);
            break;
    }

    return face;
}

/**
 * Build realistic REAR (Posterior) faceplate for a device
 */
function createDeviceRearFaceplate(dev, heightPx) {
    const classification = classifyDevice(dev);
    const cat = classification.category;
    const name = dev.name || 'Dispositivo';
    const details = dev.details || {};
    const serial = details.serial_number || '';

    const hStyle = heightPx ? `height: ${heightPx}px;` : 'height: 100%;';
    const face = $(`<div class="appliance-face" data-device-id="${dev.id || ''}" style="${hStyle}" title="${escapeHtml(name)} (${dev.height_u || 1}U) [Acceso Trasero]"></div>`);

    switch (cat) {
        case 'patch_panel':
            face.addClass('face-patch-panel-rear');
            face.html(`
                <div class="pp-label-box">
                    <span class="pp-title" style="color:#ffffff;">${escapeHtml(name)}</span>
                    <span class="pp-tag" style="color:#ffffff;">[IDC 110 PUNCH DOWN]</span>
                </div>
                <div class="pp-rear-wireblocks">
                    ${[1, 2, 3, 4].map(g => `
                        <div class="pp-idc-block" title="Bloque IDC de terminación">
                            <span class="pp-idc-pin blue" title="Par Azul"></span>
                            <span class="pp-idc-pin orange" title="Par Naranja"></span>
                            <span class="pp-idc-pin green" title="Par Verde"></span>
                            <span class="pp-idc-pin brown" title="Par Café"></span>
                        </div>
                    `).join('')}
                </div>
                <div style="display:flex; align-items:center; gap:6px;">
                    <span class="pp-ground-stud" title="Aterrizaje a Tierra"></span>
                    <span style="font-size:7px; color:#ffffff; font-weight:700;">⏚ TIERRA</span>
                </div>
            `);
            break;

        case 'organizer':
            face.addClass('face-organizer');
            face.css('background', '#181d26');
            face.html(`
                <span class="organizer-label" style="color:#ffffff;">${escapeHtml(name)} [PASACABLES TRASERO]</span>
                <div class="organizer-comb" style="opacity:0.35;"></div>
            `);
            break;

        case 'switch':
            face.addClass('face-switch-rear');
            face.html(`
                <div class="sw-info">
                    <span class="sw-model" style="color:#ffffff;">${escapeHtml(name)}</span>
                    <span class="sw-sn" style="color:#ffffff;">● DUAL AC POWER</span>
                </div>
                <div class="sw-psu-block">
                    <div class="sw-psu-module" title="Fuente Primaria PSU 1 (AC 100-240V)">
                        <div class="sw-c14-plug"></div>
                        <div class="sw-fan-vent"></div>
                        <span style="font-size:6.5px; font-weight:700; color:#ffffff;">PSU1</span>
                    </div>
                    <div class="sw-psu-module" title="Fuente Redundante PSU 2">
                        <div class="sw-c14-plug"></div>
                        <div class="sw-fan-vent"></div>
                        <span style="font-size:6.5px; font-weight:700; color:#ffffff;">PSU2</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:2px;">
                        <span class="pp-ground-stud" title="Tierra física"></span>
                        <span style="font-size:7px; color:#ffffff;">⏚</span>
                    </div>
                </div>
            `);
            break;

        case 'firewall':
            face.addClass('face-firewall-rear');
            face.html(`
                <div class="fw-info">
                    <span class="fw-title" style="color:#ffffff;">${escapeHtml(name)}</span>
                    <span class="fw-sn" style="color:#ffffff; font-weight:700;">DC 12V 3A · EXHAUST</span>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <div style="display:flex; gap:3px;">
                        <div class="sw-fan-vent" style="width:12px; height:12px;" title="Extractor térmico"></div>
                        <div class="sw-fan-vent" style="width:12px; height:12px;" title="Extractor térmico"></div>
                    </div>
                    <div class="fw-dc-jack" title="Conector Jack Poder DC 12V"></div>
                    <span class="pp-ground-stud" title="Tierra física"></span>
                </div>
            `);
            break;

        case 'router':
            face.addClass('face-router-rear');
            face.html(`
                <div class="rt-title-box">
                    <span class="rt-name" style="color:#ffffff;">${escapeHtml(name)}</span>
                    <span style="font-size:6.5px; color:#ffffff;">POWER INPUT & SECURITY</span>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <div class="rt-pwr-toggle" title="Switch On/Off">I/O</div>
                    <div class="fw-dc-jack" title="Conector DC 4-Pin"></div>
                    <div style="width:6px; height:10px; border:1px solid #64748b; border-radius:1px;" title="Kensington Lock"></div>
                </div>
            `);
            break;

        case 'shelf':
            face.addClass('face-shelf');
            face.css('background', '#334155');
            face.html(`
                <span class="shelf-label" style="color:#ffffff;">${escapeHtml(name)} [PARTE POSTERIOR]</span>
                <div class="shelf-perf" style="opacity:0.4;"></div>
            `);
            break;

        case 'nvr':
            face.addClass('face-nvr-rear');
            face.html(`
                <span class="nvr-logo" style="color:#ffffff;">${escapeHtml(name)}</span>
                <div class="nvr-rear-ports">
                    <div class="port-vga" title="VGA Out"></div>
                    <div class="port-hdmi" title="HDMI 4K Out"></div>
                    <div class="port-lan-rear" title="LAN RJ45"></div>
                    <div class="sw-fan-vent" style="width:12px; height:12px;" title="Ventilador NVR"></div>
                    <div class="rt-pwr-toggle" title="Power Switch">I/O</div>
                </div>
            `);
            break;

        case 'pdu':
            face.addClass('face-pdu-rear');
            face.html(`
                <span class="pdu-label" style="color:#ffffff;">${escapeHtml(name)} [ALIMENTACIÓN]</span>
                <div style="display:flex; align-items:center; gap:12px;">
                    <div class="pdu-breaker-btn" title="Circuit Breaker Reset (15A/20A)"></div>
                    <div class="pdu-cord-entry" title="Acometida Cable Principal AWG"></div>
                </div>
            `);
            break;

        case 'ap':
            face.addClass('face-ap-rear');
            face.html(`
                <div class="ap-rear-plate">
                    <span class="ap-name" style="color:#ffffff;">${escapeHtml(name)}</span>
                    <div class="ap-poe-port" title="Ethernet PoE In (802.3af/at)"></div>
                    <span style="font-size:6.5px; color:#ffffff; font-weight:700;">PoE IN</span>
                </div>
            `);
            break;

        case 'ups':
            face.addClass('face-ups-rear');
            face.html(`
                <div class="ups-rear-grid">
                    <div>
                        <span style="font-size:8px; font-weight:800; color:#ffffff;">APC REAR PANEL</span>
                        <div style="font-size:6.5px; color:#ffffff;">AC OUTPUT & BATTERY CONNECTOR</div>
                    </div>
                    <div style="display:flex; gap:6px; align-items:center;">
                        <div class="ups-outlets-bank" title="Banco 1: Salidas Respaldo Batería">
                            ${[1, 2, 3, 4].map(() => `<div class="ups-outlet-slot"></div>`).join('')}
                        </div>
                        <div class="ups-outlets-bank" title="Banco 2: Salidas Respaldo Batería">
                            ${[1, 2, 3, 4].map(() => `<div class="ups-outlet-slot"></div>`).join('')}
                        </div>
                        <div class="sw-fan-vent" style="width:20px; height:20px;" title="Ventilador de Enfriamiento Principal"></div>
                        <div class="ups-bat-connector" title="Conector Amarillo de Batería">BAT</div>
                    </div>
                </div>
            `);
            break;

        case 'server':
            face.addClass('face-server-rear');
            face.html(`
                <div class="sw-info">
                    <span class="sw-model" style="color:#ffffff;">${escapeHtml(name)}</span>
                    <span class="sw-sn" style="color:#ffffff;">● DUAL REDUNDANT PSU</span>
                </div>
                <div class="srv-rear-io">
                    <div class="sw-psu-block">
                        <div class="sw-psu-module" title="PSU 1 (750W Titanium)">
                            <div class="sw-c14-plug"></div>
                            <div class="sw-fan-vent"></div>
                            <span style="font-size:6.5px; font-weight:700; color:#ffffff;">750W</span>
                        </div>
                        <div class="sw-psu-module" title="PSU 2 (750W Redundante)">
                            <div class="sw-c14-plug"></div>
                            <div class="sw-fan-vent"></div>
                            <span style="font-size:6.5px; font-weight:700; color:#ffffff;">750W</span>
                        </div>
                    </div>
                    <div class="srv-nic-ports" title="Quad 1GbE/10GbE Network Ports">
                        <div class="pp-rj45"></div>
                        <div class="pp-rj45"></div>
                        <div class="pp-rj45"></div>
                        <div class="pp-rj45"></div>
                    </div>
                    <div class="srv-idrac-port" title="iDRAC / iLO Dedicated IPMI Port">
                        <span style="font-size:5.5px; color:#f59e0b; font-weight:800;">iDRAC</span>
                    </div>
                </div>
            `);
            break;

        default:
            face.css('background', '#161d26');
            face.html(`
                <div style="display:flex; justify-content:space-between; width:100%; align-items:center;">
                    <span style="font-size:8px; font-weight:800; color:#fff;">${escapeHtml(name)} [POSTERIOR]</span>
                    ${serial ? `<span style="font-size:7px; color:#cbd5e1;">S/N: ${escapeHtml(serial)}</span>` : ''}
                </div>
            `);
            break;
    }

    return face;
}

/**
 * Render Detalle por Unidad (UR) Table
 */
function renderUnitsDetailTable(rack, devices) {
    const totalU = parseInt(rack.total_u) || 19;
    const numberingDir = rack.numbering_dir || 'UP';
    const tbody = $('#ur-table-body');
    tbody.empty();

    // Render vertical PDUs (0U Lateral) if any
    const verticalPdus = devices.filter(dev => {
        const m = dev.details && (dev.details.mounting || (dev.details.is_vertical ? 'vertical_left' : ''));
        return m === 'vertical_left' || m === 'vertical_right';
    });

    if (verticalPdus.length > 0) {
        verticalPdus.forEach(pdu => {
            const sideLabel = (pdu.orientation === 'rear') ? 'Posterior' : (pdu.orientation === 'both' ? 'Frontal / Posterior' : 'Frontal');
            const mountLabel = (pdu.details && pdu.details.mounting === 'vertical_right') ? 'Lateral Der.' : 'Lateral Izq.';
            const serial = (pdu.details && pdu.details.serial_number) ? pdu.details.serial_number : '—';
            tbody.append(`
                <tr style="background: rgba(6, 182, 212, 0.08);">
                    <td class="font-weight-bold text-center" style="width: 45px;"><span class="badge badge-info" style="font-size: 10px;">0U</span></td>
                    <td>
                        <span class="dev-type-indicator" style="background-color: #06b6d4;"></span>
                        <strong>${escapeHtml(pdu.name)}</strong>
                        <span class="badge badge-light border ml-1" style="font-size: 10px;">${mountLabel} · ${sideLabel}</span>
                    </td>
                    <td class="text-muted" style="width: 120px;">${escapeHtml(serial)}</td>
                </tr>
            `);
        });
    }

    // Map units
    const uOrder = [];
    if (numberingDir === 'UP') {
        for (let u = 1; u <= totalU; u++) uOrder.push(u);
    } else {
        for (let u = totalU; u >= 1; u--) uOrder.push(u);
    }

    // Index horizontal devices
    const horizDevices = devices.filter(dev => {
        const m = dev.details && (dev.details.mounting || (dev.details.is_vertical ? 'vertical_left' : ''));
        return m !== 'vertical_left' && m !== 'vertical_right';
    });

    const devByAnchorU = {};
    const occupiedUnits = new Set();
    horizDevices.forEach(dev => {
        const startU = parseInt(dev.start_u);
        const hU = Math.max(1, parseInt(dev.height_u) || 1);
        const anchorU = (numberingDir === 'DOWN') ? (startU + hU - 1) : startU;
        if (!devByAnchorU[anchorU]) devByAnchorU[anchorU] = [];
        devByAnchorU[anchorU].push(dev);
        for (let u = startU; u < startU + hU; u++) {
            occupiedUnits.add(u);
        }
    });

    uOrder.forEach(u => {
        if (devByAnchorU[u] && devByAnchorU[u].length > 0) {
            devByAnchorU[u].forEach(dev => {
                const startU = parseInt(dev.start_u);
                const hU = Math.max(1, parseInt(dev.height_u) || 1);
                const uRange = (hU > 1) ? (numberingDir === 'DOWN' ? `${startU + hU - 1}-${startU}` : `${startU}-${startU + hU - 1}`) : `${startU}`;
                const classification = classifyDevice(dev);
                const serial = (dev.details && dev.details.serial_number) ? dev.details.serial_number : '—';
                const badgeSide = (dev.orientation === 'rear') ? '<span class="badge badge-warning ml-1" style="font-size: 9px;">Rear</span>' : '<span class="badge badge-light border ml-1" style="font-size: 9px;">Front</span>';

                tbody.append(`
                    <tr>
                        <td class="font-weight-bold text-center" style="width: 45px;">${uRange}</td>
                        <td>
                            <span class="dev-type-indicator" style="background-color: ${classification.color};"></span>
                            <strong>${escapeHtml(dev.name)}</strong>
                            ${badgeSide}
                        </td>
                        <td class="text-muted" style="width: 120px;">${escapeHtml(serial)}</td>
                    </tr>
                `);
            });
        } else if (!occupiedUnits.has(u)) {
            tbody.append(`
                <tr>
                    <td class="font-weight-bold text-center" style="width: 45px;">${u}</td>
                    <td>
                        <span class="dev-type-indicator" style="background-color: #cbd5e1;"></span>
                        <span class="ur-empty-text">vacío</span>
                    </td>
                    <td class="text-muted" style="width: 120px;">—</td>
                </tr>
            `);
        }
    });
}

/**
 * Render Observations Card
 */
function renderObservations(obsList) {
    const container = $('#info-observations-list');
    container.empty();

    if (!obsList || obsList.length === 0) {
        obsList = [
            'Gabinete operativo en producción',
            'Sin observaciones críticas reportadas'
        ];
    }

    obsList.forEach(item => {
        container.append(`<li>${escapeHtml(item)}</li>`);
    });
}

/**
 * Render Evidence Photographic Grid (Exclusivo para fotos del Rack)
 */
function renderEvidencePhotos(photos) {
    const mainPhotoContainer = $('#info-main-photo-box');
    const subGridContainer = $('#info-subphotos-grid');
    mainPhotoContainer.empty();
    subGridContainer.empty();

    if (!photos || photos.length === 0) {
        mainPhotoContainer.html(`
            <div class="photos-empty-state text-center py-4 px-2" style="background: rgba(15, 23, 42, 0.6); border-radius: 8px; border: 1px dashed #334155;">
                <i class="fas fa-camera fa-2x mb-2 text-muted"></i>
                <div class="small font-weight-bold text-white mb-1">Sin evidencia fotográfica registrada</div>
                <div class="text-muted small mb-3" style="font-size: 11px;">Solo se mostrarán las fotos subidas para este rack específico.</div>
                <button type="button" class="btn btn-xs btn-outline-info font-weight-bold" onclick="triggerRackPhotoUpload()">
                    <i class="fas fa-cloud-upload-alt mr-1"></i> Subir Foto de Campo
                </button>
            </div>
        `);
        return;
    }

    // First photo as main
    const mainPhoto = photos[0];
    let mainUrl = mainPhoto.url;
    if (!mainUrl.startsWith('http') && !mainUrl.startsWith('/') && !mainUrl.startsWith('assets/')) {
        mainUrl = '../' + mainUrl;
    }

    mainPhotoContainer.html(`
        <div class="photo-card-main position-relative" style="overflow: hidden; border-radius: 8px;" onclick="openPhotoLightbox('${mainUrl}', '${escapeHtml(mainPhoto.title)}')">
            <img src="${mainUrl}" alt="${escapeHtml(mainPhoto.title)}" style="width: 100%; object-fit: cover; cursor: pointer;">
            <button type="button" class="btn btn-xs btn-danger" style="position: absolute; top: 6px; right: 6px; z-index: 10; padding: 2px 6px; opacity: 0.85;" title="Eliminar fotografía" onclick="deleteRackPhoto(event, '${escapeHtml(mainPhoto.url)}')">
                <i class="fas fa-trash-alt"></i>
            </button>
        </div>
        <div class="photo-caption text-truncate mt-1 small text-muted" title="${escapeHtml(mainPhoto.title)}">${escapeHtml(mainPhoto.title || 'Vista general del gabinete')}</div>
    `);

    // Remaining photos in 2-column grid
    const remaining = photos.slice(1);
    remaining.forEach(photo => {
        let photoUrl = photo.url;
        if (!photoUrl.startsWith('http') && !photoUrl.startsWith('/') && !photoUrl.startsWith('assets/')) {
            photoUrl = '../' + photoUrl;
        }

        subGridContainer.append(`
            <div class="position-relative mb-2">
                <div class="photo-sub-item position-relative" style="overflow: hidden; border-radius: 6px;" onclick="openPhotoLightbox('${photoUrl}', '${escapeHtml(photo.title)}')">
                    <img src="${photoUrl}" alt="${escapeHtml(photo.title)}" style="width: 100%; height: 75px; object-fit: cover; cursor: pointer;">
                    <button type="button" class="btn btn-xs btn-danger" style="position: absolute; top: 4px; right: 4px; z-index: 10; padding: 1px 5px; opacity: 0.85;" title="Eliminar fotografía" onclick="deleteRackPhoto(event, '${escapeHtml(photo.url)}')">
                        <i class="fas fa-trash-alt" style="font-size: 0.65rem;"></i>
                    </button>
                </div>
                <div class="photo-caption text-truncate small text-muted" style="font-size: 0.72rem;" title="${escapeHtml(photo.title)}">${escapeHtml(photo.title || 'Foto de campo')}</div>
            </div>
        `);
    });
}

/**
 * Trigger Photo Upload Modal for current Rack
 */
function triggerRackPhotoUpload() {
    if (!currentRackData || !currentRackData.rack) {
        alert('Seleccione un rack primero.');
        return;
    }
    const rackId = currentRackData.rack.id;
    $('#upload_photo_rack_id').val(rackId);
    $('#upload_photo_title').val('');
    $('#upload_photo_file').val('');
    $('#upload_photo_preview_box').hide();
    $('#modalUploadRackPhotoTitle').html(`<i class="fas fa-camera mr-2 text-info"></i>Subir Fotografía - ${escapeHtml(currentRackData.rack.name)}`);
    $('#modalUploadRackPhoto').modal('show');
}

/**
 * Preview selected image before uploading
 */
function previewUploadPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            $('#upload_photo_preview_img').attr('src', e.target.result);
            $('#upload_photo_preview_box').show();
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        $('#upload_photo_preview_box').hide();
    }
}

/**
 * Submit Rack Photo Upload Form via AJAX
 */
function submitRackPhotoUpload(e) {
    e.preventDefault();
    const rackId = $('#upload_photo_rack_id').val();
    if (!rackId) return;

    const fileInput = document.getElementById('upload_photo_file');
    if (!fileInput.files || fileInput.files.length === 0) {
        alert('Debe seleccionar una imagen.');
        return;
    }

    const formData = new FormData(document.getElementById('formUploadRackPhoto'));
    const btn = $('#btn_submit_rack_photo');
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Subiendo...');

    $.ajax({
        url: 'api.php?action=save_rack_evidence',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        dataType: 'json',
        success: function(res) {
            btn.prop('disabled', false).html('<i class="fas fa-cloud-upload-alt mr-1"></i> Subir Foto');
            if (res.success) {
                $('#modalUploadRackPhoto').modal('hide');
                if (currentRackData && currentRackData.rack) {
                    currentRackData.rack.photos = res.photos || [];
                }
                renderEvidencePhotos(res.photos || []);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Fotografía subida correctamente',
                        showConfirmButton: false,
                        timer: 2500
                    });
                }
            } else {
                alert('Error al subir fotografía: ' + (res.message || 'Error desconocido'));
            }
        },
        error: function(xhr, status, error) {
            btn.prop('disabled', false).html('<i class="fas fa-cloud-upload-alt mr-1"></i> Subir Foto');
            alert('Error de conexión al subir fotografía: ' + error);
        }
    });
}

/**
 * Delete a photo from rack evidence
 */
function deleteRackPhoto(e, photoUrl) {
    if (e) e.stopPropagation();
    if (!currentRackData || !currentRackData.rack) return;
    const rackId = currentRackData.rack.id;

    if (!confirm('¿Está seguro de eliminar esta fotografía de evidencia?')) {
        return;
    }

    $.ajax({
        url: 'api.php?action=delete_rack_photo',
        type: 'POST',
        data: {
            rack_id: rackId,
            photo_url: photoUrl
        },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                if (currentRackData && currentRackData.rack) {
                    currentRackData.rack.photos = res.photos || [];
                }
                renderEvidencePhotos(res.photos || []);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Fotografía eliminada',
                        showConfirmButton: false,
                        timer: 2000
                    });
                }
            } else {
                alert('No se pudo eliminar la foto: ' + (res.message || 'Error'));
            }
        },
        error: function(xhr, status, error) {
            alert('Error al contactar con el servidor: ' + error);
        }
    });
}

/**
 * Lightbox preview for clicking photos
 */
function openPhotoLightbox(url, title) {
    $('#lightboxImg').attr('src', url);
    $('#lightboxTitle').text(title || 'Evidencia Fotográfica');
    $('#photoLightboxModal').modal('show');
}

/**
 * Export high-resolution PNG using html2canvas
 */
function downloadRackInfographicImage() {
    const card = document.getElementById('rack-infographic-card');
    if (!card) return;

    const btn = $('#btnExportImage');
    const originalText = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Generando Imagen...');

    // Options for html2canvas to ensure crisp retina quality
    const opt = {
        scale: 2, // 2x scale for sharp text and vectors
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff',
        logging: false,
        scrollX: 0,
        scrollY: 0
    };

    html2canvas(card, opt).then(canvas => {
        btn.prop('disabled', false).html(originalText);

        const imgData = canvas.toDataURL('image/png');
        const link = document.createElement('a');

        const rackName = (currentRackData && currentRackData.rack && currentRackData.rack.name) ? currentRackData.rack.name.replace(/[^a-zA-Z0-9_-]/g, '_') : 'RACK';
        const location = (currentRackData && currentRackData.rack && currentRackData.rack.location) ? currentRackData.rack.location.replace(/[^a-zA-Z0-9_-]/g, '_') : 'SITE';
        const sideSuffix = (currentViewSide === 'front') ? 'FRONTAL' : ((currentViewSide === 'rear') ? 'POSTERIOR' : 'DUAL');

        link.download = `DIAGRAMA_RACK_${location}_${rackName}_${sideSuffix}.png`;
        link.href = imgData;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }).catch(err => {
        btn.prop('disabled', false).html(originalText);
        alert('Error al generar la imagen del rack: ' + err);
        console.error(err);
    });
}

/**
 * Print Infographic
 */
function printRackInfographic() {
    window.print();
}

/**
 * Utility: HTML escape
 */
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}
