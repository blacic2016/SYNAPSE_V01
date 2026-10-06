let myDiagram = null;

const faUnicodeMap = {
    'fa-cube': '\uf1b2',
    'fa-server': '\uf233',
    'fa-globe': '\uf0ac',
    'fa-building': '\uf1ad',
    'fa-network-wired': '\uf6ff',
    'fa-user-shield': '\uf3f4',
    'fa-laptop': '\uf109',
    'fa-mobile-alt': '\uf3cd',
    'fa-database': '\uf1c0',
    'fa-desktop': '\uf108',
    'fa-shadow-alt': '\uf3ed',
    'fa-hdd': '\uf0a0',
    'fa-print': '\uf02f',
    'fa-ethernet': '\uf796',
    'fa-wifi': '\uf1eb',
    'fa-key': '\uf084',
    'fa-users': '\uf0c0',
    'fa-envelope': '\uf0e0',
    'fa-phone': '\uf095',
    'fa-cogs': '\uf085',
    'fa-folder': '\uf07b',
    'fa-file-alt': '\uf15c',
    'fa-door-open': '\uf52b',
    'fa-city': '\uf64f',
    'fa-microchip': '\uf2db',
    'fa-ship': '\uf21a',
    'fa-tachometer-alt': '\uf3fd',
    'fa-map': '\uf279',
    'fa-code': '\uf121',
    'fa-user': '\uf007'
};

function getUnicodeIcon(iconClass) {
    if (!iconClass) return '\uf1b2';
    let parts = iconClass.split(' ');
    let cleanClass = parts[parts.length - 1].trim();
    return faUnicodeMap[cleanClass] || '\uf1b2';
}

$(document).ready(function() {
    initDiagram();
    loadGraphData();
});

function initDiagram() {
    const $ = go.GraphObject.make;

    myDiagram = $(go.Diagram, "myDiagramDiv", {
        "undoManager.isEnabled": true,
        layout: $(go.LayeredDigraphLayout, { 
            direction: 0, // Horizontal layout (left-to-right)
            layerSpacing: 120, // Spacing for bezier curves
            columnSpacing: 60,
            setsPortSpots: false
        }),
        initialContentAlignment: go.Spot.Center,
        "animationManager.isEnabled": true
    });

    // Plantilla para los CIs (Nodos)
    myDiagram.nodeTemplate = $(go.Node, "Vertical",
        { 
            locationSpot: go.Spot.Center,
            click: (e, obj) => {
                showRelations(obj.data.key);
            },
            fromSpot: go.Spot.Right,
            toSpot: go.Spot.Left
        },
        new go.Binding("location", "loc", go.Point.parse).makeTwoWay(go.Point.stringify),
        new go.Binding("visible", "visible"),
        
        // 1. Contenedor del Icono
        $(go.Panel, "Spot",
            $(go.Shape, "Circle", {
                fill: "#ffffff",
                stroke: "#cbd5e1",
                strokeWidth: 2,
                width: 44,
                height: 44
            },
            new go.Binding("stroke", "status", s => s === 'Activo' ? "#10b981" : (s === 'Pasivo' ? '#f59e0b' : "#ef4444"))
            ),
            $(go.TextBlock, 
                { 
                    font: '900 16pt "Font Awesome 5 Free"', 
                    stroke: "#3b82f6",
                    alignment: go.Spot.Center
                },
                new go.Binding("text", "icon", getUnicodeIcon),
                new go.Binding("stroke", "status", s => s === 'Activo' ? "#10b981" : (s === 'Pasivo' ? '#f59e0b' : "#ef4444"))
            )
        ),
        
        // Espaciador
        $(go.Shape, { width: 1, height: 6, fill: "transparent", stroke: null }),
        
        // 2. Contenedor del Texto (Pill)
        $(go.Panel, "Auto",
            $(go.Shape, "RoundedRectangle", 
                { 
                    fill: "#ffffff", 
                    stroke: "#cbd5e1", 
                    strokeWidth: 1.5, 
                    parameter1: 6
                },
                new go.Binding("stroke", "isSelected", sel => sel ? "#3b82f6" : "#cbd5e1"),
                new go.Binding("strokeWidth", "isSelected", sel => sel ? 2 : 1.5)
            ),
            $(go.Panel, "Vertical", { margin: new go.Margin(6, 12, 6, 12), defaultAlignment: go.Spot.Center },
                // Nombre del CI
                $(go.TextBlock, 
                    { 
                        font: "bold 8.5pt system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif", 
                        stroke: "#1e293b",
                        margin: new go.Margin(0, 0, 2, 0)
                    },
                    new go.Binding("text", "name")
                ),
                // Categoría
                $(go.TextBlock, 
                    { 
                        font: "bold 6.5pt system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif", 
                        stroke: "#64748b" 
                    },
                    new go.Binding("text", "categoryName", name => name ? name.toUpperCase() : "")
                )
            )
        ),
        
        // 3. Botón Expander
        $("Button",
            {
                margin: new go.Margin(4, 0, 0, 0),
                click: (e, obj) => {
                    e.stopPropagation();
                    toggleNodeRelations(obj.part);
                },
                "ButtonBorder.stroke": "#cbd5e1",
                "ButtonBorder.fill": "#f8fafc",
                "ButtonBorder.strokeWidth": 1,
                "ButtonBorder.figure": "Circle",
                width: 18,
                height: 18
            },
            $(go.TextBlock,
                { 
                    font: "bold 7pt system-ui, sans-serif", 
                    stroke: "#64748b",
                    alignment: go.Spot.Center
                },
                new go.Binding("text", "relationsCollapsed", collapsed => collapsed ? "+" : "-")
            )
        )
    );

    // Plantilla de Relaciones (Enlaces)
    myDiagram.linkTemplate = $(go.Link,
        { 
            routing: go.Link.Normal, 
            curve: go.Link.Bezier,
            fromSpot: go.Spot.Right, 
            toSpot: go.Spot.Left
        },
        $(go.Shape, 
            { strokeWidth: 1.5, stroke: "#cbd5e1" },
            new go.Binding("stroke", "isSelected", sel => sel ? "#3b82f6" : "#cbd5e1"),
            new go.Binding("strokeWidth", "isSelected", sel => sel ? 2.5 : 1.5)
        ),
        $(go.Shape, 
            { toArrow: "Standard", stroke: null, fill: "#cbd5e1" },
            new go.Binding("fill", "isSelected", sel => sel ? "#3b82f6" : "#cbd5e1")
        ),
        $(go.Panel, "Auto",
            new go.Binding("visible", "type", t => t && t !== ''),
            $(go.Shape, "RoundedRectangle", { fill: "#ffffff", stroke: "#e2e8f0", strokeWidth: 1, parameter1: 4 }),
            $(go.TextBlock, { margin: new go.Margin(3, 6, 3, 6), font: "italic 7.5pt system-ui, sans-serif", stroke: "#475569" },
                new go.Binding("text", "type")
            )
        )
    );
}

function toggleNodeRelations(node) {
    let diagram = node.diagram;
    diagram.startTransaction("toggle relations");
    
    let collapsed = node.data.relationsCollapsed;
    diagram.model.setDataProperty(node.data, "relationsCollapsed", !collapsed);
    
    node.findLinksConnected().each(l => {
        let other = l.getOtherNode(node);
        if (other !== null && other.data) {
            diagram.model.setDataProperty(other.data, "visible", collapsed);
        }
    });
    
    diagram.commitTransaction("toggle relations");
}

function loadGraphData() {
    $('#diagram-loader').removeClass('d-none').addClass('d-flex').show();
    
    let url = 'api_ci.php?action=get_ci_business_view';
    if (typeof FOCUS_CI_ID !== 'undefined' && FOCUS_CI_ID > 0) {
        url += '&ci_id=' + FOCUS_CI_ID;
    }

    $.get(url, function(res) {
        $('#diagram-loader').removeClass('d-flex').addClass('d-none').hide();
        if (res.success) {
            let nodeDataArray = [];
            let linkDataArray = [];
            
            // Build a set of visible CI IDs initially
            let visibleCIs = new Set();
            if (typeof FOCUS_CI_ID !== 'undefined' && FOCUS_CI_ID > 0) {
                visibleCIs.add(FOCUS_CI_ID);
            } else {
                res.data.cis.forEach(ci => visibleCIs.add(ci.id));
            }
            
            // 1. Crear Nodos (CIs)
            res.data.cis.forEach(ci => {
                let isVisible = visibleCIs.has(ci.id);
                nodeDataArray.push({
                    key: 'ci_' + ci.id,
                    name: ci.hostname,
                    status: ci.status,
                    icon: ci.icon,
                    categoryName: ci.category_name,
                    visible: isVisible,
                    relationsCollapsed: true
                });
            });
            
            // 2. Crear Enlaces (Relaciones entre CIs)
            res.data.relationships.forEach(rel => {
                linkDataArray.push({
                    from: 'ci_' + rel.source_id,
                    to: 'ci_' + rel.target_id,
                    type: rel.relation_type
                });
            });
            
            myDiagram.model = new go.GraphLinksModel(nodeDataArray, linkDataArray);

            setTimeout(() => {
                if (typeof FOCUS_CI_ID !== 'undefined' && FOCUS_CI_ID > 0) {
                    showRelations('ci_' + FOCUS_CI_ID);
                    let node = myDiagram.findNodeForKey('ci_' + FOCUS_CI_ID);
                    if (node !== null) {
                        myDiagram.centerRect(node.actualBounds);
                    }
                }
            }, 150);

        } else {
            Swal.fire('Error', res.message, 'error');
        }
    }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
        $('#diagram-loader').removeClass('d-flex').addClass('d-none').hide();
        Swal.fire('Error', 'Error al cargar los datos de la red', 'error');
        console.error(jqXHR.responseText);
    });
}

function zoomToFit() {
    if (myDiagram) myDiagram.zoomToFit();
}

function showRelations(ciKey) {
    myDiagram.clearSelection();
    let node = myDiagram.findNodeForKey(ciKey);
    if (node !== null) {
        myDiagram.select(node);
        node.findNodesConnected().each(n => {
            n.isSelected = true;
        });
        node.findLinksConnected().each(l => {
            l.isSelected = true;
        });
    }
}
