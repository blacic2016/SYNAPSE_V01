import re
import json

with open("extracted_text.txt", "r", encoding="utf-8") as f:
    text = f.read()

pages = text.split("--- PAGE ")

apis = []

# Hardcoded details or heuristic parser for high quality
# Let's map each page's API to build a rich dataset
api_catalog = {
    2: {
        "title": "Creación de Casos",
        "method": "POST",
        "uri": "/api/v9/item",
        "description": "Permite crear un nuevo caso (Incidente, Problema, Cambio, Requerimiento de servicio, Liberación) en Aranda Service Management.",
        "params": [
            {"name": "applicantId", "type": "Int", "required": "No", "description": "Identificador del solicitante del caso."},
            {"name": "authorId", "type": "Int", "required": "No", "description": "Identificador del autor del caso."},
            {"name": "categoryId", "type": "Int", "required": "Sí", "description": "Identificador de la categoría del caso."},
            {"name": "cause", "type": "String", "required": "No", "description": "Descripción de la causa raíz (si itemType es 2 - Problema)."},
            {"name": "ciId", "type": "Int", "required": "No", "description": "Identificador del CI."},
            {"name": "companyId", "type": "Int", "required": "No", "description": "Identificador de la compañía."},
            {"name": "consoleType", "type": "String", "required": "Sí", "description": "Tipo de consola (Specialist=1, Client=2, Administrator=3, CMDB=4)."},
            {"name": "description", "type": "String", "required": "No", "description": "Descripción del caso (puede incluir HTML)."},
            {"name": "groupId", "type": "Int", "required": "No", "description": "Identificador del grupo de especialistas."},
            {"name": "itemType", "type": "Int", "required": "Sí", "description": "Tipo de caso: 1.Incidente, 2.Problema, 3.Cambio, 4.Requerimiento, 13.Liberación."},
            {"name": "modelId", "type": "Int", "required": "Sí", "description": "Identificador del modelo operativo."},
            {"name": "projectId", "type": "Int", "required": "Sí", "description": "Identificador del proyecto."},
            {"name": "serviceId", "type": "Int", "required": "Sí", "description": "Identificador del servicio."},
            {"name": "stateId", "type": "Int", "required": "Sí", "description": "Identificador del estado inicial."}
        ],
        "request_body": {
            "applicantId": 1252367,
            "categoryId": 1941,
            "consoleType": "Specialist",
            "description": "Incidente creado a través de API de integración",
            "itemType": 1,
            "modelId": 16,
            "projectId": 2,
            "serviceId": 834,
            "stateId": 1
        }
    },
    7: {
        "title": "Consulta de Casos",
        "method": "GET",
        "uri": "/api/v9/item/{ItemId}",
        "description": "Obtiene la información detallada de un caso a partir de su identificador único (ItemId).",
        "params": [
            {"name": "ItemId", "type": "Int", "required": "Sí", "description": "Identificador único del caso."}
        ],
        "request_body": None
    },
    10: {
        "title": "Edición de Caso",
        "method": "PUT",
        "uri": "/api/v9/item/{ItemId}",
        "description": "Permite editar y actualizar campos específicos de un caso existente.",
        "params": [
            {"name": "ItemId", "type": "Int", "required": "Sí", "description": "Identificador único del caso."},
            {"name": "description", "type": "String", "required": "No", "description": "Nueva descripción del caso."},
            {"name": "groupId", "type": "Int", "required": "No", "description": "Nuevo grupo de especialistas."},
            {"name": "responsibleId", "type": "Int", "required": "No", "description": "Nuevo responsable del caso."}
        ],
        "request_body": {
            "description": "Descripción actualizada del caso",
            "groupId": 102,
            "responsibleId": 1252367
        }
    },
    15: {
        "title": "Lista de Casos",
        "method": "POST",
        "uri": "/api/v9/item/search",
        "description": "Busca y pagina un listado de casos utilizando varios criterios de filtrado.",
        "params": [
            {"name": "ConsoleType", "type": "String", "required": "Sí", "description": "Consola que realiza la búsqueda (Specialist / Client)."},
            {"name": "ProjectId", "type": "Int", "required": "Sí", "description": "Identificador del proyecto."},
            {"name": "Itemtype", "type": "Int", "required": "No", "description": "Tipo de caso (1 = Incidente, etc.)."}
        ],
        "request_body": {
            "ConsoleType": "Specialist",
            "ProjectId": 2,
            "Itemtype": 1
        }
    },
    20: {
        "title": "Cargar Archivos Adjuntos",
        "method": "POST",
        "uri": "/api/v9/file",
        "description": "Permite cargar un archivo adjunto de manera temporal para ser asociado a un caso.",
        "params": [
            {"name": "file", "type": "Binary", "required": "Sí", "description": "Archivo físico a subir."}
        ],
        "request_body": None
    },
    22: {
        "title": "Agregar Notas",
        "method": "POST",
        "uri": "/api/v9/item/{id}/note",
        "description": "Agrega una nota o comentario público/privado a un caso específico.",
        "params": [
            {"name": "id", "type": "Int", "required": "Sí", "description": "Identificador único del caso."},
            {"name": "Content", "type": "String", "required": "Sí", "description": "Contenido de la nota."},
            {"name": "IsPrivate", "type": "Bool", "required": "Sí", "description": "Indica si la nota es privada (solo especialistas)."}
        ],
        "request_body": {
            "Content": "Comentario de seguimiento de la API",
            "IsPrivate": False
        }
    },
    23: {
        "title": "Obtener Estados",
        "method": "GET",
        "uri": "/api/v9/model/{id}/{itemType}/states",
        "description": "Obtiene la lista de estados válidos para un modelo operativo y tipo de caso.",
        "params": [
            {"name": "id", "type": "Int", "required": "Sí", "description": "Identificador del modelo operativo."},
            {"name": "itemType", "type": "Int", "required": "Sí", "description": "Tipo de caso (1 = Incidente, etc.)."},
            {"name": "stateId", "type": "Int", "required": "No", "description": "Identificador del estado actual (opcional)."},
            {"name": "itemId", "type": "Int", "required": "No", "description": "Identificador del caso (opcional)."}
        ],
        "request_body": None
    },
    25: {
        "title": "Eliminar Archivos Adjuntos",
        "method": "DELETE",
        "uri": "/api/v9/file/{id}",
        "description": "Elimina un archivo adjunto del caso o de la carpeta temporal.",
        "params": [
            {"name": "id", "type": "String", "required": "Sí", "description": "Identificador único del archivo."},
            {"name": "uploadType", "type": "Int", "required": "Sí", "description": "Tipo de subida."}
        ],
        "request_body": None
    },
    26: {
        "title": "Listar Archivos de un Caso",
        "method": "GET",
        "uri": "/api/v9/item/{id}/files",
        "description": "Obtiene la lista de todos los archivos adjuntos asociados a un caso específico.",
        "params": [
            {"name": "id", "type": "Int", "required": "Sí", "description": "Identificador único del caso."},
            {"name": "itemType", "type": "Int", "required": "Sí", "description": "Tipo de caso."},
            {"name": "uploadType", "type": "Int", "required": "Sí", "description": "Tipo de subida."}
        ],
        "request_body": None
    },
    28: {
        "title": "Lista de Históricos",
        "method": "GET",
        "uri": "/api/v9/item/{id}/history/list",
        "description": "Obtiene el historial de eventos, cambios de estado y acciones ejecutadas sobre un caso.",
        "params": [
            {"name": "id", "type": "Int", "required": "Sí", "description": "Identificador único del caso."},
            {"name": "isClosed", "type": "Bool", "required": "No", "description": "Filtrar por histórico cerrado."},
            {"name": "consoleType", "type": "String", "required": "No", "description": "Tipo de consola."}
        ],
        "request_body": None
    },
    31: {
        "title": "Agregar Tarea",
        "method": "POST",
        "uri": "/api/v9/task",
        "description": "Permite crear y asignar una nueva tarea secundaria dentro de un caso padre.",
        "params": [
            {"name": "parentId", "type": "Int", "required": "Sí", "description": "Identificador del caso padre."},
            {"name": "description", "type": "String", "required": "Sí", "description": "Detalle de la tarea."},
            {"name": "responsibleId", "type": "Int", "required": "No", "description": "Especialista responsable."}
        ],
        "request_body": {
            "parentId": 12523,
            "description": "Revisar configuración de base de datos",
            "responsibleId": 1252367
        }
    },
    34: {
        "title": "Lista de Tareas",
        "method": "POST",
        "uri": "/api/v9/item/{id}/type/{itemType}/relations/list",
        "description": "Lista todas las tareas secundarias y relaciones asociadas al caso.",
        "params": [
            {"name": "id", "type": "Int", "required": "Sí", "description": "Identificador único del caso."},
            {"name": "itemType", "type": "Int", "required": "Sí", "description": "Tipo de caso."},
            {"name": "repository", "type": "String", "required": "No", "description": "Filtro de repositorio."}
        ],
        "request_body": None
    },
    38: {
        "title": "Agregar Relaciones",
        "method": "POST",
        "uri": "/api/v9/item/{id}/relation",
        "description": "Establece una relación de impacto, dependencia u otra entre dos casos.",
        "params": [
            {"name": "id", "type": "Int", "required": "Sí", "description": "Identificador único del caso de origen."},
            {"name": "relatedItemId", "type": "Int", "required": "Sí", "description": "Identificador único del caso de destino."},
            {"name": "relationTypeId", "type": "Int", "required": "Sí", "description": "Tipo de relación."}
        ],
        "request_body": {
            "relatedItemId": 12524,
            "relationTypeId": 1
        }
    },
    40: {
        "title": "Lista de Relaciones",
        "method": "POST",
        "uri": "/api/v9/item/{id}/type/{itemType}/relations/list",
        "description": "Obtiene la lista de relaciones (casos vinculados) asociadas a un caso específico.",
        "params": [
            {"name": "id", "type": "Int", "required": "Sí", "description": "Identificador del caso."},
            {"name": "itemType", "type": "Int", "required": "Sí", "description": "Tipo de caso."}
        ],
        "request_body": None
    },
    44: {
        "title": "Eliminar Relaciones",
        "method": "DELETE",
        "uri": "/api/v9/item/{id}/{itemType}/{relatedItemId}/{relatedItemType}/relation",
        "description": "Elimina una relación existente entre dos casos.",
        "params": [
            {"name": "id", "type": "Int", "required": "Sí", "description": "Identificador del caso de origen."},
            {"name": "relatedItemId", "type": "Int", "required": "Sí", "description": "Identificador del caso de destino."}
        ],
        "request_body": None
    },
    46: {
        "title": "Obtener Modelo por Categoría",
        "method": "GET",
        "uri": "/api/v9/item/{itemType}/categories/{categoryId}/service/{serviceId}/model",
        "description": "Obtiene el modelo operativo y su flujo de estados asociado según la categoría y servicio.",
        "params": [
            {"name": "itemType", "type": "Int", "required": "Sí", "description": "Tipo de caso."},
            {"name": "categoryId", "type": "Int", "required": "Sí", "description": "Categoría."},
            {"name": "serviceId", "type": "Int", "required": "Sí", "description": "Servicio."}
        ],
        "request_body": None
    },
    47: {
        "title": "Listar Casos Padres (Relación Complementaria)",
        "method": "GET",
        "uri": "/api/v9/item/list/parent/{childId}/close",
        "description": "Lista los casos padres que se cerrarían automáticamente al cerrar un caso hijo específico.",
        "params": [
            {"name": "childId", "type": "Int", "required": "Sí", "description": "Identificador del caso hijo."}
        ],
        "request_body": None
    },
    48: {
        "title": "Cerrar Casos Padres (Relación Complementaria)",
        "method": "POST",
        "uri": "/api/v9/item/close/related/parent",
        "description": "Realiza el cierre automático de los casos padres que tienen relación complementaria con un hijo cerrado.",
        "params": [
            {"name": "childId", "type": "Int", "required": "Sí", "description": "Identificador del caso hijo."}
        ],
        "request_body": {
            "childId": 12523
        }
    },
    50: {
        "title": "Búsqueda de Usuario por Tipo",
        "method": "GET",
        "uri": "/api/v9/user/{id}/search",
        "description": "Busca usuarios filtrando por rol, proyecto u otros criterios en relación a un usuario.",
        "params": [
            {"name": "id", "type": "Int", "required": "Sí", "description": "Identificador del usuario que consulta."},
            {"name": "itemType", "type": "String", "required": "Sí", "description": "Tipo de elemento."},
            {"name": "projectId", "type": "Int", "required": "Sí", "description": "Identificador del proyecto."}
        ],
        "request_body": None
    },
    52: {
        "title": "Edición de Usuarios",
        "method": "PUT",
        "uri": "/api/v9/user/{id}",
        "description": "Permite modificar datos del perfil de un usuario existente.",
        "params": [
            {"name": "id", "type": "Int", "required": "Sí", "description": "Identificador único del usuario."},
            {"name": "name", "type": "String", "required": "No", "description": "Nombre completo."},
            {"name": "email", "type": "String", "required": "No", "description": "Correo electrónico."}
        ],
        "request_body": {
            "name": "Usuario Integración Modificado",
            "email": "srvcl_qa49@sndint63loc.cl"
        }
    },
    55: {
        "title": "Creación de Usuarios",
        "method": "POST",
        "uri": "/api/v9/user",
        "description": "Permite crear un nuevo usuario en Aranda Service Management.",
        "params": [
            {"name": "userName", "type": "String", "required": "Sí", "description": "Nombre de usuario único."},
            {"name": "name", "type": "String", "required": "Sí", "description": "Nombre real del usuario."},
            {"name": "email", "type": "String", "required": "Sí", "description": "Correo electrónico."}
        ],
        "request_body": {
            "userName": "user_api_test",
            "name": "Usuario Test API",
            "email": "testapi@sonda.com"
        }
    },
    58: {
        "title": "Detalle de Usuario",
        "method": "GET",
        "uri": "/api/v9/user",
        "description": "Obtiene la información de perfil detallada de un usuario a partir de su ID de usuario.",
        "params": [
            {"name": "id", "type": "Int", "required": "Sí", "description": "Identificador del usuario."}
        ],
        "request_body": None
    },
    61: {
        "title": "Búsqueda de Usuarios Global",
        "method": "GET",
        "uri": "/api/v9/user/searchAll",
        "description": "Obtiene listados de especialistas, clientes o administradores según filtros.",
        "params": [
            {"name": "projectId", "type": "Int", "required": "Sí", "description": "ID del proyecto."},
            {"name": "itemType", "type": "String", "required": "Sí", "description": "Tipo de usuario (specialist/client/admin)."}
        ],
        "request_body": None
    },
    62: {
        "title": "Asociación de Usuarios a Compañía",
        "method": "POST",
        "uri": "/api/v9/company/{company_id}/associateusers",
        "description": "Asocia una lista de IDs de usuarios a una compañía específica.",
        "params": [
            {"name": "company_id", "type": "Int", "required": "Sí", "description": "Identificador de la compañía."},
            {"name": "userIds", "type": "Array", "required": "Sí", "description": "Lista de identificadores de usuario."}
        ],
        "request_body": {
            "userIds": [1252367]
        }
    },
    63: {
        "title": "Compañías de un Usuario",
        "method": "GET",
        "uri": "/api/v9/user/{user_id}/companies",
        "description": "Lista todas las compañías a las que pertenece o está asociado un usuario.",
        "params": [
            {"name": "user_id", "type": "Int", "required": "Sí", "description": "Identificador del usuario."}
        ],
        "request_body": None
    },
    65: {
        "title": "Lista de Categorías",
        "method": "GET",
        "uri": "/api/v9/item/{itemType}/services/{serviceId}/categories",
        "description": "Obtiene el árbol o lista de categorías configuradas para un servicio específico.",
        "params": [
            {"name": "itemType", "type": "Int", "required": "Sí", "description": "Tipo de caso."},
            {"name": "serviceId", "type": "Int", "required": "Sí", "description": "Identificador de servicio."}
        ],
        "request_body": None
    },
    68: {
        "title": "Manejo de Servicios (Búsqueda)",
        "method": "GET",
        "uri": "/api/v9/project/{id}/{itemType}/services/search",
        "description": "Obtiene la lista de servicios activos asociados a un proyecto para un tipo de caso.",
        "params": [
            {"name": "id", "type": "Int", "required": "Sí", "description": "Identificador del proyecto."},
            {"name": "itemType", "type": "Int", "required": "Sí", "description": "Tipo de caso."},
            {"name": "console", "type": "String", "required": "Sí", "description": "Tipo de consola (Specialist/Client)."}
        ],
        "request_body": None
    },
    70: {
        "title": "Lista de Campos Adicionales",
        "method": "POST",
        "uri": "/api/v9/item/additionalfields",
        "description": "Consulta los campos adicionales obligatorios u opcionales definidos para un caso.",
        "params": [
            {"name": "CategoryId", "type": "Int", "required": "Sí", "description": "Categoría del caso."},
            {"name": "ProjectId", "type": "Int", "required": "Sí", "description": "Proyecto del caso."}
        ],
        "request_body": {
            "CategoryId": 1941,
            "ProjectId": 2
        }
    },
    73: {
        "title": "Valores de Campos Catálogo",
        "method": "GET",
        "uri": "/api/v9/additionalfields/{id}/type/{fieldType}/values",
        "description": "Obtiene los valores permitidos para campos adicionales que son de tipo lista o catálogo.",
        "params": [
            {"name": "id", "type": "Int", "required": "Sí", "description": "Identificador del campo adicional."},
            {"name": "fieldType", "type": "Int", "required": "Sí", "description": "Tipo de campo."}
        ],
        "request_body": None
    },
    76: {
        "title": "Creación de Compañías",
        "method": "POST",
        "uri": "/api/v9/company",
        "description": "Crea una nueva compañía o proveedor en la base de datos de Aranda.",
        "params": [
            {"name": "name", "type": "String", "required": "Sí", "description": "Nombre de la compañía."},
            {"name": "nit", "type": "String", "required": "No", "description": "NIT o Identificador fiscal."}
        ],
        "request_body": {
            "name": "Compañía API Test",
            "nit": "900.123.456-7"
        }
    },
    81: {
        "title": "Edición de Compañía",
        "method": "PUT",
        "uri": "/api/v9/company/{id}",
        "description": "Permite actualizar los datos de una compañía específica.",
        "params": [
            {"name": "id", "type": "Int", "required": "Sí", "description": "Identificador de la compañía."},
            {"name": "name", "type": "String", "required": "No", "description": "Nuevo nombre."}
        ],
        "request_body": {
            "name": "Compañía API Test Modificada"
        }
    },
    84: {
        "title": "Búsqueda de Compañía",
        "method": "GET",
        "uri": "/api/v9/company/search",
        "description": "Busca compañías o proveedores a partir de criterios específicos.",
        "params": [
            {"name": "itemType", "type": "String", "required": "Sí", "description": "Tipo de elemento."},
            {"name": "projectId", "type": "Int", "required": "Sí", "description": "Identificador del proyecto."}
        ],
        "request_body": None
    },
    88: {
        "title": "Campos Adicionales de Compañía",
        "method": "GET",
        "uri": "/api/v9/company/additionalfields",
        "description": "Obtiene los campos adicionales definidos para la entidad compañía.",
        "params": [],
        "request_body": None
    },
    91: {
        "title": "Obtener Compañía por Nombre",
        "method": "GET",
        "uri": "/api/v9/company/getbyname",
        "description": "Busca una compañía a partir de su nombre exacto o parcial.",
        "params": [
            {"name": "companyName", "type": "String", "required": "Sí", "description": "Nombre a buscar."},
            {"name": "projectId", "type": "Int", "required": "Sí", "description": "ID de proyecto."}
        ],
        "request_body": None
    },
    92: {
        "title": "Asociar Proyectos a Compañía",
        "method": "POST",
        "uri": "/api/v9/company/addproject",
        "description": "Asocia uno o más proyectos a una compañía específica.",
        "params": [
            {"name": "companyId", "type": "Int", "required": "Sí", "description": "ID de la compañía."},
            {"name": "projectIds", "type": "Array", "required": "Sí", "description": "Arreglo de IDs de proyectos."}
        ],
        "request_body": {
            "companyId": 1,
            "projectIds": [2, 3]
        }
    },
    93: {
        "title": "Compañías de un Usuario (Listado)",
        "method": "GET",
        "uri": "/api/v9/user/{Id}/usercompanies",
        "description": "Lista todas las compañías autorizadas o vinculadas al usuario especificado.",
        "params": [
            {"name": "Id", "type": "Int", "required": "Sí", "description": "Identificador del usuario."}
        ],
        "request_body": None
    },
    95: {
        "title": "Obtener Catálogo por ID",
        "method": "GET",
        "uri": "/api/v9/catalog/{id}",
        "description": "Obtiene el detalle de un catálogo específico configurado en el sistema.",
        "params": [
            {"name": "id", "type": "Int", "required": "Sí", "description": "ID del catálogo."}
        ],
        "request_body": None
    },
    96: {
        "title": "Obtener Catálogos por Tipo",
        "method": "GET",
        "uri": "/api/v9/catalog/type={type}",
        "description": "Lista los catálogos disponibles filtrando por tipo.",
        "params": [
            {"name": "type", "type": "Int", "required": "Sí", "description": "Tipo de catálogo."}
        ],
        "request_body": None
    },
    99: {
        "title": "Grupos por Proyecto",
        "method": "GET",
        "uri": "/api/v9/group/GetGroupsByProjects",
        "description": "Lista los grupos de especialistas asociados a uno o varios proyectos.",
        "params": [
            {"name": "projectIds", "type": "String", "required": "Sí", "description": "Cadena con IDs de proyectos separados por coma."}
        ],
        "request_body": None
    }
}

for page_idx, val in api_catalog.items():
    apis.append({
        "page": page_idx,
        "title": val["title"],
        "method": str(val["method"]).upper(),
        "uri": val["uri"],
        "description": val["description"],
        "params": val["params"],
        "request_body": val["request_body"]
    })

# Save to JSON
with open("apis.json", "w", encoding="utf-8") as f:
    json.dump(apis, f, indent=4, ensure_ascii=False)

print(f"Successfully generated apis.json with {len(apis)} entries")
