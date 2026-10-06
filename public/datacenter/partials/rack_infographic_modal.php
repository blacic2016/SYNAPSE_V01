<!-- MODAL VIEW DIAGRAMA INFOGRÁFICO DE RACK (SOLO LECTURA) -->
<div class="modal fade" id="rackViewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered rack-view-modal-dialog" role="document">
        <div class="modal-content rack-view-modal-content">
            <!-- Modal Header with Action Toolbar -->
            <div class="rack-view-modal-header">
                <div class="d-flex align-items-center" style="gap: 12px;">
                    <span class="badge badge-light text-dark font-weight-bold px-2 py-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                        <i class="fas fa-lock mr-1 text-primary"></i>SOLO LECTURA
                    </span>
                    <h5 class="modal-title font-weight-bold m-0" style="font-size: 1.05rem;">
                        <i class="fas fa-eye mr-2"></i>Vista de Rack · Diagrama Infográfico
                    </h5>
                </div>
                <div class="d-flex align-items-center" style="gap: 8px;">
                    <!-- Selector Frontal / Trasera / Dual -->
                    <div class="btn-group btn-group-sm mr-2" role="group">
                        <button type="button" class="btn btn-light font-weight-bold view-side-btn active" id="btnSideFront" onclick="setRackViewSide('front')">
                            <i class="fas fa-desktop mr-1"></i> Frontal
                        </button>
                        <button type="button" class="btn btn-outline-light font-weight-bold view-side-btn" id="btnSideRear" onclick="setRackViewSide('rear')">
                            <i class="fas fa-server mr-1"></i> Trasera / Posterior
                        </button>
                        <button type="button" class="btn btn-outline-light font-weight-bold view-side-btn" id="btnSideDual" onclick="setRackViewSide('dual')">
                            <i class="fas fa-columns mr-1"></i> Ambas Vistas
                        </button>
                    </div>

                    <button type="button" class="btn btn-success btn-sm font-weight-bold shadow-sm" id="btnExportImage" onclick="downloadRackInfographicImage()">
                        <i class="fas fa-camera mr-1"></i> Descargar Imagen (PNG)
                    </button>
                    <button type="button" class="btn btn-light btn-sm font-weight-bold shadow-sm" onclick="printRackInfographic()">
                        <i class="fas fa-print mr-1"></i> Imprimir
                    </button>
                    <button type="button" class="close text-white ml-2" data-dismiss="modal" aria-label="Close" style="opacity: 0.9;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>

            <div class="rack-view-modal-body">
                <!-- Loading State -->
                <div id="rack-infographic-loading" class="text-center py-5">
                    <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
                    <h6 class="text-muted font-weight-bold">Cargando diagrama e inventario del bastidor...</h6>
                </div>

                <!-- Error State -->
                <div id="rack-infographic-error" class="alert alert-danger mx-auto my-4 text-center" style="max-width: 600px; display: none;">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2 d-block"></i>
                    <span id="rack-infographic-error-text">No se pudo cargar la información.</span>
                </div>

                <!-- Master Infographic Card (HTML2Canvas Export Target) -->
                <div id="rack-infographic-card" style="display: none;">
                    <!-- Infographic Header -->
                    <div class="info-header">
                        <div class="info-brand">
                            <div class="info-brand-logo">
                                <!-- Vilaseca / Client SVG Logo -->
                                <svg width="150" height="52" viewBox="0 0 200 65" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <g transform="translate(10, 8)">
                                        <!-- Sunburst rays matching Vilaseca branding -->
                                        <ellipse cx="24" cy="9" rx="3.5" ry="8" fill="#00a896" />
                                        <ellipse cx="35" cy="14" rx="3.5" ry="8" transform="rotate(45 35 14)" fill="#f39c12" />
                                        <ellipse cx="39" cy="24" rx="3.5" ry="8" transform="rotate(90 39 24)" fill="#e74c3c" />
                                        <ellipse cx="35" cy="34" rx="3.5" ry="8" transform="rotate(135 35 34)" fill="#2c3e50" />
                                        <ellipse cx="24" cy="39" rx="3.5" ry="8" fill="#f1c40f" />
                                        <ellipse cx="14" cy="34" rx="3.5" ry="8" transform="rotate(-135 14 34)" fill="#3498db" />
                                        <ellipse cx="9" cy="24" rx="3.5" ry="8" transform="rotate(-90 9 24)" fill="#e67e22" />
                                        <ellipse cx="14" cy="14" rx="3.5" ry="8" transform="rotate(-45 14 14)" fill="#1abc9c" />
                                    </g>
                                    <text x="65" y="24" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="11" font-weight="700" fill="#7f8c8d" letter-spacing="3">GRUPO</text>
                                    <text x="65" y="44" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="20" font-weight="900" fill="#2c3e50" letter-spacing="1">VILASECA</text>
                                </svg>
                            </div>
                            <div class="info-title-box">
                                <h2 id="info-site-title">DALKASA</h2>
                                <div class="info-subtitle" id="info-diagram-subtitle">DIAGRAMA DE RACK · RACK 01 · OFICINA LOGÍSTICA</div>
                                <div class="info-subclient" id="info-client-name">GRUPO VILASECA</div>
                            </div>
                        </div>

                        <div class="info-badges">
                            <span class="info-pill" id="info-badge-total-u">19U</span>
                            <span class="info-pill" id="info-badge-type">AÉREO</span>
                            <div class="info-view-switch" role="group">
                                <button type="button" class="info-pill-btn active" id="pill-view-front" onclick="setRackViewSide('front')">VISTA FRONTAL</button>
                                <button type="button" class="info-pill-btn" id="pill-view-rear" onclick="setRackViewSide('rear')">VISTA POSTERIOR</button>
                                <button type="button" class="info-pill-btn" id="pill-view-dual" onclick="setRackViewSide('dual')">AMBAS VISTAS</button>
                            </div>
                        </div>
                    </div>

                    <!-- Dual-color Divider -->
                    <div class="info-divider">
                        <div class="info-div-teal"></div>
                        <div class="info-div-orange"></div>
                    </div>

                    <!-- 3-Column Body -->
                    <div class="info-body-grid" id="info-body-grid-container">
                        <!-- Column 1: Cabinet Frontal -->
                        <div id="col-cabinet-front">
                            <div class="cabinet-3d-wrapper">
                                <!-- Top Perspective Plate -->
                                <div class="cabinet-top-face">
                                    <div class="top-vent-grill">SIN EXTRACTOR</div>
                                </div>
                                <div class="cabinet-main-frame">
                                    <div class="cabinet-front">
                                        <div class="cabinet-header-plate" id="cabinet-header-text">
                                            RACK 01 · OFICINA LOGÍSTICA
                                        </div>
                                        <div class="cabinet-interior">
                                            <div class="rack-rail-left" id="cabinet-rail-left"></div>
                                            <div class="rack-pdu-channel left-channel" id="cabinet-pdu-col-left" style="display: none;"></div>
                                            <div class="rack-slots-column" id="cabinet-slots-col"></div>
                                            <div class="rack-pdu-channel right-channel" id="cabinet-pdu-col-right" style="display: none;"></div>
                                            <div class="rack-rail-right" id="cabinet-rail-right"></div>
                                        </div>
                                        <div class="cabinet-footer-plate" id="cabinet-footer-text">
                                            19U · AÉREO
                                        </div>
                                    </div>
                                    <div class="cabinet-right-side"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Column 1-Rear: Cabinet Posterior (Visible in Rear or Dual mode) -->
                        <div id="col-cabinet-rear" style="display: none;">
                            <div class="cabinet-3d-wrapper rear-cabinet">
                                <!-- Top Perspective Plate -->
                                <div class="cabinet-top-face">
                                    <div class="top-vent-grill">ACCESO POSTERIOR</div>
                                </div>
                                <div class="cabinet-main-frame">
                                    <div class="cabinet-front">
                                        <div class="cabinet-header-plate" id="cabinet-rear-header-text">
                                            RACK 01 · VISTA POSTERIOR
                                        </div>
                                        <div class="cabinet-interior">
                                            <div class="rack-pdu-channel left-channel rear-outer-pdu" id="cabinet-rear-pdu-col-left" style="display: none;"></div>
                                            <div class="rack-rail-left" id="cabinet-rear-rail-left"></div>
                                            <div class="rack-slots-column" id="cabinet-rear-slots-col"></div>
                                            <div class="rack-rail-right" id="cabinet-rear-rail-right"></div>
                                            <div class="rack-pdu-channel right-channel rear-outer-pdu" id="cabinet-rear-pdu-col-right" style="display: none;"></div>
                                        </div>
                                        <div class="cabinet-footer-plate" id="cabinet-rear-footer-text">
                                            19U · ACCESO POSTERIOR
                                        </div>
                                    </div>
                                    <div class="cabinet-right-side"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Column 2: Stats, Table, Observations, Legend -->
                        <div class="info-center-col">
                            <!-- Summary Cards -->
                            <div class="kpi-cards-row">
                                <div class="kpi-stat-card">
                                    <div class="kpi-stat-num teal" id="kpi-total-u">19</div>
                                    <div class="kpi-stat-label">UR Totales</div>
                                </div>
                                <div class="kpi-stat-card">
                                    <div class="kpi-stat-num green" id="kpi-occupied-u">13</div>
                                    <div class="kpi-stat-label">UR Ocupadas</div>
                                </div>
                                <div class="kpi-stat-card">
                                    <div class="kpi-stat-num orange" id="kpi-free-u">6</div>
                                    <div class="kpi-stat-label">UR Libres</div>
                                </div>
                            </div>

                            <!-- Occupancy Bar -->
                            <div class="occupancy-bar-box">
                                <div class="occupancy-labels">
                                    <span>OCUPACIÓN</span>
                                    <span id="info-occupancy-pct">68 %</span>
                                </div>
                                <div class="occupancy-progress">
                                    <div class="occupancy-fill" id="info-occupancy-bar" style="width: 68%;"></div>
                                </div>
                            </div>

                            <!-- Detalle por Unidad (UR) Table -->
                            <div class="ur-detail-box">
                                <div class="ur-detail-title">DETALLE POR UNIDAD (UR)</div>
                                <div class="ur-table-container">
                                    <table class="ur-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 45px;" class="text-center">UR</th>
                                                <th>ELEMENTO</th>
                                                <th style="width: 120px;">SERIE</th>
                                            </tr>
                                        </thead>
                                        <tbody id="ur-table-body">
                                            <!-- Dynamically populated -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Observaciones Box -->
                            <div class="observations-box">
                                <div class="observations-title">
                                    <i class="fas fa-exclamation-triangle text-warning"></i>
                                    OBSERVACIONES
                                </div>
                                <ul class="observations-list" id="info-observations-list">
                                    <!-- Dynamically populated -->
                                </ul>
                            </div>

                            <!-- Leyenda Box -->
                            <div class="legend-box">
                                <div class="legend-title">LEYENDA</div>
                                <div class="legend-grid">
                                    <div class="legend-item"><span class="legend-swatch" style="background:#334155;"></span>Patch panel</div>
                                    <div class="legend-item"><span class="legend-swatch" style="background:#475569;"></span>Organizador de cable</div>
                                    <div class="legend-item"><span class="legend-swatch" style="background:#0284c7;"></span>Switch</div>

                                    <div class="legend-item"><span class="legend-swatch" style="background:#dc2626;"></span>Firewall</div>
                                    <div class="legend-item"><span class="legend-swatch" style="background:#1e40af;"></span>Router</div>
                                    <div class="legend-item"><span class="legend-swatch" style="background:#94a3b8;"></span>Bandeja</div>

                                    <div class="legend-item"><span class="legend-swatch" style="background:#7c3aed;"></span>NVR</div>
                                    <div class="legend-item"><span class="legend-swatch" style="background:#d97706;"></span>PDU</div>
                                    <div class="legend-item"><span class="legend-swatch" style="background:#16a34a;"></span>Access point</div>

                                    <div class="legend-item"><span class="legend-swatch" style="background:#1e293b;"></span>UPS</div>
                                    <div class="legend-item"><span class="legend-swatch" style="background:#cbd5e1;"></span>Vacío</div>
                                    <div class="legend-item"><span class="legend-swatch circle" style="background:#22c55e;"></span>LED activo</div>

                                    <div class="legend-item"><span class="legend-swatch circle" style="background:#475569;"></span>LED inactivo</div>
                                    <div class="legend-item"><span class="legend-swatch circle" style="background:#f97316;"></span>Sin registro</div>
                                </div>
                            </div>
                        </div>

                        <!-- Column 3: Evidencia Fotográfica -->
                        <div class="info-photos-col">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <h6 class="photos-header-title m-0">EVIDENCIA FOTOGRÁFICA</h6>
                                    <p class="photos-header-sub m-0">Fotos exclusivas de este gabinete</p>
                                </div>
                                <button type="button" class="btn btn-xs btn-outline-info font-weight-bold shadow-xs" id="btn-add-rack-photo" onclick="triggerRackPhotoUpload()" title="Subir fotografía para este rack">
                                    <i class="fas fa-camera mr-1"></i> Subir Foto
                                </button>
                            </div>

                            <!-- Main Photo -->
                            <div id="info-main-photo-box">
                                <!-- Populated dynamically -->
                            </div>

                            <!-- Secondary Photos 2-col Grid -->
                            <div class="photos-subgrid" id="info-subphotos-grid">
                                <!-- Populated dynamically -->
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="info-footer" id="info-footer-text">
                        Fuente: CMDB VILASECA / DCIM • * datos tomados de las fotos de campo • Vista frontal - UR 1 arriba • Generado el 01/10/2026
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA SUBIR FOTOGRAFÍA AL RACK -->
<div class="modal fade" id="modalUploadRackPhoto" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden; background: #ffffff;">
            <div class="modal-header py-2 px-3 text-white" style="background: linear-gradient(135deg, #101B31 0%, #1e293b 100%); border-bottom: 2px solid var(--sonda-cyan);">
                <h6 class="modal-title font-weight-bold" id="modalUploadRackPhotoTitle">
                    <i class="fas fa-camera mr-2 text-info"></i>Subir Fotografía de Evidencia
                </h6>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-3">
                <form id="formUploadRackPhoto" onsubmit="submitRackPhotoUpload(event)">
                    <input type="hidden" id="upload_photo_rack_id" name="rack_id" value="">
                    <div class="form-group mb-2">
                        <label class="small font-weight-bold text-secondary mb-1">Título / Descripción de la Foto:</label>
                        <input type="text" class="form-control form-control-sm font-weight-bold" id="upload_photo_title" name="photo_title" placeholder="Ej: Vista frontal del gabinete, Detalle de switch" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="small font-weight-bold text-secondary mb-1">Seleccionar Imagen (JPG, PNG, WEBP):</label>
                        <input type="file" class="form-control-file" id="upload_photo_file" name="new_photo" accept="image/jpeg,image/png,image/webp" required onchange="previewUploadPhoto(this)">
                    </div>
                    <div id="upload_photo_preview_box" class="text-center mb-2" style="display:none;">
                        <img id="upload_photo_preview_img" src="" alt="Previsualización" style="max-height: 150px; max-width: 100%; border-radius: 6px; border: 1px solid #cbd5e1;">
                    </div>
                    <div class="d-flex justify-content-end" style="gap: 8px;">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                        <button type="submit" id="btn_submit_rack_photo" class="btn btn-info btn-sm font-weight-bold">
                            <i class="fas fa-cloud-upload-alt mr-1"></i> Subir Foto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL LIGHTBOX FOTOS -->
<div class="modal fade" id="photoLightboxModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content bg-dark text-white border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header py-2 px-3 border-secondary" style="background: #111827;">
                <h6 class="modal-title font-weight-bold" id="lightboxTitle">Evidencia Fotográfica</h6>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-0 text-center bg-black">
                <img id="lightboxImg" src="" alt="Evidencia" style="max-height: 80vh; max-width: 100%; object-fit: contain;">
            </div>
        </div>
    </div>
</div>
