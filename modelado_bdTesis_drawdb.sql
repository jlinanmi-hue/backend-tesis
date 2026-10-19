-- Modelado fiel de bdTesis (SQL Server) para drawDB. Solo diagramar, no ejecutar contra la BD real.

CREATE TABLE [ai_generation_feedback] (
  [id] bigint NOT NULL,
  [session_id] nvarchar(100) NOT NULL,
  [message_id] nvarchar(100) NULL,
  [tool_log_id] bigint NULL,
  [tool_name] nvarchar(100) NULL,
  [pedido_id] nvarchar(50) NULL,
  [tipo_operacion] nvarchar(60) NOT NULL,
  [rating] nvarchar(20) NOT NULL,
  [es_correcto] bit NOT NULL,
  [motivo_dislike] nvarchar(100) NULL,
  [comentario] nvarchar(MAX) NULL,
  [user_id] bigint NULL,
  [created_at] datetime NULL,
  [updated_at] datetime NULL,
  PRIMARY KEY ([id])
);

CREATE TABLE [ai_intent_logs] (
  [id] bigint NOT NULL,
  [session_id] nvarchar(100) NULL,
  [user_id] bigint NULL,
  [intent] nvarchar(50) NOT NULL,
  [query] nvarchar(MAX) NOT NULL,
  [complexity] nvarchar(20) NOT NULL,
  [model_used] nvarchar(100) NULL,
  [tool_used] nvarchar(100) NULL,
  [tools_sent] int NOT NULL,
  [tokens_used] int NOT NULL,
  [gemini_ms] int NOT NULL,
  [tool_ms] int NOT NULL,
  [total_ms] int NOT NULL,
  [cache_hit] bit NOT NULL,
  [success] bit NOT NULL,
  [error_message] nvarchar(500) NULL,
  [created_at] datetime NULL,
  [updated_at] datetime NULL,
  PRIMARY KEY ([id])
);

CREATE TABLE [ai_tool_logs] (
  [id] bigint NOT NULL,
  [user_id] bigint NULL,
  [session_id] nvarchar(100) NOT NULL,
  [tool_name] nvarchar(100) NOT NULL,
  [pedido_id] nvarchar(50) NULL,
  [args] nvarchar(MAX) NOT NULL,
  [result] nvarchar(MAX) NULL,
  [status] nvarchar(30) NOT NULL,
  [duration_ms] int NOT NULL,
  [prompt_tokens] int NOT NULL,
  [candidates_tokens] int NOT NULL,
  [total_tokens] int NOT NULL,
  [error_message] nvarchar(500) NULL,
  [created_at] datetime NULL,
  [updated_at] datetime NULL,
  [cost_usd] decimal(10,6) NOT NULL,
  [feedback] nvarchar(20) NULL,
  [motivo_dislike] nvarchar(100) NULL,
  [feedback_at] datetime NULL,
  PRIMARY KEY ([id])
);

CREATE TABLE [Ajuste_inventario] (
  [Ajuste_inventario_id] nvarchar(20) NOT NULL,
  [Ajuste_inventario_ProductoId] nvarchar(20) NOT NULL,
  [Ajuste_inventario_tipo] nvarchar(30) NOT NULL,
  [Ajuste_inventario_cantidad] decimal(10,2) NOT NULL,
  [Ajuste_inventario_motivo] nvarchar(255) NOT NULL,
  [Ajuste_inventario_fecha] datetime NOT NULL,
  [Ajuste_inventario_Eliminado] nchar(1) NOT NULL,
  [Ajuste_inventario_UsuarioCreacion] nvarchar(100) NOT NULL,
  [Ajuste_inventario_HostCreacion] nvarchar(100) NOT NULL,
  [Ajuste_inventario_FechaCreacion] datetime NOT NULL,
  [Ajuste_inventario_UsuarioModificacion] nvarchar(100) NULL,
  [Ajuste_inventario_HostModificacion] nvarchar(100) NULL,
  [Ajuste_inventario_FechaModificacion] datetime NULL,
  [Ajuste_inventario_UsuarioEliminacion] nvarchar(100) NULL,
  [Ajuste_inventario_HostEliminacion] nvarchar(100) NULL,
  [Ajuste_inventario_FechaEliminacion] datetime NULL,
  PRIMARY KEY ([Ajuste_inventario_id])
);

CREATE TABLE [auditoria_roturas_stock] (
  [id] bigint NOT NULL,
  [pedido_id] nvarchar(20) NULL,
  [cantidad_solicitada] decimal(12,2) NOT NULL,
  [cantidad_disponible_fisica] decimal(12,2) NOT NULL,
  [deficit_unidades] decimal(12,2) NOT NULL,
  [tipo_rotura] nvarchar(30) NOT NULL,
  [rompe_stock_seguridad] bit NOT NULL,
  [categoria_id] nvarchar(20) NULL,
  [usuario] nvarchar(50) NULL,
  [created_at] datetime NULL,
  PRIMARY KEY ([id])
);

CREATE TABLE [Canal_pedido] (
  [Canal_pedidoId] nvarchar(20) NOT NULL,
  [Canal_pedidoDescripcion] nvarchar(100) NOT NULL,
  [Canal_pedidoEstado] nchar(1) NOT NULL,
  [Canal_pedidoEliminado] nchar(1) NOT NULL,
  [Canal_pedidoUsuarioCreacion] nvarchar(100) NOT NULL,
  [Canal_pedidoHostCreacion] nvarchar(100) NOT NULL,
  [Canal_pedidoFechaCreacion] datetime NOT NULL,
  [Canal_pedidoUsuarioModificacion] nvarchar(100) NULL,
  [Canal_pedidoHostModificacion] nvarchar(100) NULL,
  [Canal_pedidoFechaModificacion] datetime NULL,
  [Canal_pedidoUsuarioEliminacion] nvarchar(100) NULL,
  [Canal_pedidoHostEliminacion] nvarchar(100) NULL,
  [Canal_pedidoFechaEliminacion] datetime NULL,
  PRIMARY KEY ([Canal_pedidoId])
);

CREATE TABLE [cargo] (
  [cargoId] nvarchar(20) NOT NULL,
  [cargoDescripcion] nvarchar(45) NOT NULL,
  [cargoEstado] nchar(1) NOT NULL,
  [cargoEliminado] nchar(1) NOT NULL,
  [cargoUsuarioCreacion] nvarchar(100) NOT NULL,
  [cargoHostCreacion] nvarchar(100) NOT NULL,
  [cargoFechaCreacion] datetime NOT NULL,
  [cargoUsuarioModificacion] nvarchar(100) NULL,
  [cargoHostModificacion] nvarchar(100) NULL,
  [cargoFechaModificacion] datetime NULL,
  [cargoUsuarioEliminacion] nvarchar(100) NULL,
  [cargoHostEliminacion] nvarchar(100) NULL,
  [cargoFechaEliminacion] datetime NULL,
  PRIMARY KEY ([cargoId])
);

CREATE TABLE [Categoria_Producto] (
  [Categoria_ProductoId] nvarchar(20) NOT NULL,
  [Categoria_ProductoDescripcion_categoria] nvarchar(45) NOT NULL,
  [Categoria_ProductoEstado] nchar(1) NOT NULL,
  [Categoria_ProductoEliminado] nchar(1) NOT NULL,
  [Categoria_ProductoUsuarioCreacion] nvarchar(100) NOT NULL,
  [Categoria_ProductoHostCreacion] nvarchar(100) NOT NULL,
  [Categoria_ProductoFechaCreacion] datetime NOT NULL,
  [Categoria_ProductoUsuarioModificacion] nvarchar(100) NULL,
  [Categoria_ProductoHostModificacion] nvarchar(100) NULL,
  [Categoria_ProductoFechaModificacion] datetime NULL,
  [Categoria_ProductoUsuarioEliminacion] nvarchar(100) NULL,
  [Categoria_ProductoHostEliminacion] nvarchar(100) NULL,
  [Categoria_ProductoFechaEliminacion] datetime NULL,
  PRIMARY KEY ([Categoria_ProductoId])
);

CREATE TABLE [Cliente] (
  [ClienteId] nvarchar(20) NOT NULL,
  [ClienteNombre] nvarchar(100) NOT NULL,
  [ClienteDireccion] nvarchar(200) NOT NULL,
  [ClienteNumero] nvarchar(45) NOT NULL,
  [ClienteEstado] nchar(1) NOT NULL,
  [ClienteRuc] nvarchar(45) NOT NULL,
  [ClienteEliminado] nchar(1) NOT NULL,
  [ClienteUsuarioCreacion] nvarchar(100) NOT NULL,
  [ClienteHostCreacion] nvarchar(100) NOT NULL,
  [ClienteFechaCreacion] datetime NOT NULL,
  [ClienteUsuarioModificacion] nvarchar(100) NULL,
  [ClienteHostModificacion] nvarchar(100) NULL,
  [ClienteFechaModificacion] datetime NULL,
  [ClienteUsuarioEliminacion] nvarchar(100) NULL,
  [ClienteHostEliminacion] nvarchar(100) NULL,
  [ClienteFechaEliminacion] datetime NULL,
  [ClienteComprasCount] int NOT NULL,
  [ClienteTotalMonto] decimal(12,2) NOT NULL,
  [ClienteUltimaCompra] datetime NULL,
  [ClienteOrigen] nvarchar(20) NULL,
  PRIMARY KEY ([ClienteId])
);

CREATE TABLE [Consulta_Ruc] (
  [ConsultaRucId] bigint NOT NULL,
  [ConsultaRucNumero] nvarchar(11) NOT NULL,
  [ConsultaRucRazonSocial] nvarchar(255) NOT NULL,
  [ConsultaRucEstado] nvarchar(50) NOT NULL,
  [ConsultaRucCondicion] nvarchar(50) NOT NULL,
  [ConsultaRucDireccion] nvarchar(500) NOT NULL,
  [ConsultaRucDepartamento] nvarchar(100) NULL,
  [ConsultaRucProvincia] nvarchar(100) NULL,
  [ConsultaRucDistrito] nvarchar(100) NULL,
  [ConsultaRucUbigeo] nvarchar(20) NULL,
  [ConsultaRucTipoContribuyente] nvarchar(150) NULL,
  [ConsultaRucFechaInscripcion] nvarchar(50) NULL,
  [ConsultaRucActividadEconomica] nvarchar(255) NULL,
  [ConsultaRucTelefono] nvarchar(50) NULL,
  [ConsultaRucRawJson] nvarchar(MAX) NULL,
  [ConsultaRucVecesConsultado] int NOT NULL,
  [ConsultaRucUltimaConsulta] datetime NOT NULL,
  [ConsultaRucUsuarioCreacion] nvarchar(100) NULL,
  [ConsultaRucHostCreacion] nvarchar(100) NULL,
  [ConsultaRucFechaCreacion] datetime NOT NULL,
  [ConsultaRucUsuarioModificacion] nvarchar(100) NULL,
  [ConsultaRucHostModificacion] nvarchar(100) NULL,
  [ConsultaRucFechaModificacion] datetime NULL,
  PRIMARY KEY ([ConsultaRucId])
);

CREATE TABLE [Detalle_Orden_Compra] (
  [Detalle_Orden_CompraId] int NOT NULL,
  [Detalle_Orden_Compra_Orden_CompraId] nvarchar(20) NOT NULL,
  [Detalle_ProductoId] nvarchar(20) NOT NULL,
  [Detalle_UnidadMedidaId] nvarchar(20) NOT NULL,
  [Detalle_Orden_CompraCantidad] decimal(10,2) NOT NULL,
  [Detalle_Orden_CompraPrecioUnitario] decimal(10,2) NOT NULL,
  [Detalle_Orden_CompraSubtotal] decimal(10,2) NOT NULL,
  [Detalle_Orden_CompraEliminado] nchar(1) NOT NULL,
  [Detalle_Orden_CompraUsuarioCreacion] nvarchar(100) NOT NULL,
  [Detalle_Orden_CompraHostCreacion] nvarchar(100) NOT NULL,
  [Detalle_Orden_CompraFechaCreacion] datetime NOT NULL,
  [Detalle_Orden_CompraUsuarioModificacion] nvarchar(100) NULL,
  [Detalle_Orden_CompraHostModificacion] nvarchar(100) NULL,
  [Detalle_Orden_CompraFechaModificacion] datetime NULL,
  [Detalle_Orden_CompraUsuarioEliminacion] nvarchar(100) NULL,
  [Detalle_Orden_CompraHostEliminacion] nvarchar(100) NULL,
  [Detalle_Orden_CompraFechaEliminacion] datetime NULL,
  [Detalle_Orden_CompraCantidadRecibida] decimal(10,2) NOT NULL,
  [Detalle_Orden_CompraEntregado] nchar(1) NOT NULL,
  [Detalle_Orden_CompraFechaRecepcionItem] datetime NULL,
  [Detalle_Orden_CompraUsuarioRecepcionItemId] nvarchar(100) NULL,
  [Detalle_Orden_CompraFueProductoNuevo] nchar(1) NOT NULL,
  PRIMARY KEY ([Detalle_Orden_CompraId])
);

CREATE TABLE [Detalle_Pedido_Productos] (
  [Detalle_Pedido_ProductosId] int NOT NULL,
  [Detalle_Pedido_Productos_PedidoId] nvarchar(20) NOT NULL,
  [Detalle_Pedido_Productos_ProductoId] nvarchar(20) NOT NULL,
  [Detalle_Pedido_Productos_cantidad] decimal(10,2) NOT NULL,
  [Detalle_Pedido_Productos_precio_unitario_venta] decimal(10,2) NOT NULL,
  [Detalle_Pedido_Productos_subtotal] decimal(10,2) NOT NULL,
  [Detalle_Pedido_ProductosEliminado] nchar(1) NOT NULL,
  [Detalle_Pedido_ProductosUsuarioCreacion] nvarchar(100) NOT NULL,
  [Detalle_Pedido_ProductosHostCreacion] nvarchar(100) NOT NULL,
  [Detalle_Pedido_ProductosFechaCreacion] datetime NOT NULL,
  [Detalle_Pedido_ProductosUsuarioModificacion] nvarchar(100) NULL,
  [Detalle_Pedido_ProductosHostModificacion] nvarchar(100) NULL,
  [Detalle_Pedido_ProductosFechaModificacion] datetime NULL,
  [Detalle_Pedido_ProductosUsuarioEliminacion] nvarchar(100) NULL,
  [Detalle_Pedido_ProductosHostEliminacion] nvarchar(100) NULL,
  [Detalle_Pedido_ProductosFechaEliminacion] datetime NULL,
  [Detalle_Pedido_Productos_cantidad_fisica] decimal(10,2) NOT NULL,
  [Detalle_Pedido_Productos_unidades_medidaId] nvarchar(20) NULL,
  [Detalle_Pedido_Productos_factor_conversion] decimal(10,2) NOT NULL,
  [Detalle_Pedido_Productos_cantidad_base] decimal(10,2) NOT NULL,
  [Detalle_Pedido_ProductosTuvoRotura] nchar(1) NOT NULL,
  [Detalle_Pedido_ProductosCantidadFaltanteRotura] decimal(10,2) NOT NULL,
  [Detalle_Pedido_Productos_cantidad_despachada] decimal(18,2) NULL,
  [Detalle_Pedido_Productos_estado_item_despacho] nvarchar(30) NULL,
  [Detalle_Pedido_Productos_observacion_item] nvarchar(200) NULL,
  PRIMARY KEY ([Detalle_Pedido_ProductosId])
);

CREATE TABLE [Detalle_Producto_medida] (
  [Detalle_Producto_medida_ProductoId] nvarchar(20) NOT NULL,
  [Detalle_Producto_medida_unidades_medidaId] nvarchar(20) NOT NULL,
  [Detalle_Producto_medida_factor_conversion] int NOT NULL,
  [Detalle_Producto_medida_precio_venta] decimal(10,2) NOT NULL,
  [Detalle_Producto_medida_precio_compra] decimal(10,2) NOT NULL,
  [Detalle_Producto_medidaEliminado] nchar(1) NOT NULL,
  [Detalle_Producto_medidaUsuarioCreacion] nvarchar(100) NOT NULL,
  [Detalle_Producto_medidaHostCreacion] nvarchar(100) NOT NULL,
  [Detalle_Producto_medidaFechaCreacion] datetime NOT NULL,
  [Detalle_Producto_medidaUsuarioModificacion] nvarchar(100) NULL,
  [Detalle_Producto_medidaHostModificacion] nvarchar(100) NULL,
  [Detalle_Producto_medidaFechaModificacion] datetime NULL,
  [Detalle_Producto_medidaUsuarioEliminacion] nvarchar(100) NULL,
  [Detalle_Producto_medidaHostEliminacion] nvarchar(100) NULL,
  [Detalle_Producto_medidaFechaEliminacion] datetime NULL,
  PRIMARY KEY ([Detalle_Producto_medida_ProductoId], [Detalle_Producto_medida_unidades_medidaId])
);

CREATE TABLE [detalle_rotura_stock] (
  [id] bigint NOT NULL,
  [pedido_id] nvarchar(20) NULL,
  [detalle_pedido_id] int NULL,
  [producto_id] nvarchar(20) NOT NULL,
  [orden_compra_codigo] nvarchar(20) NULL,
  [cantidad_solicitada] decimal(12,2) NOT NULL,
  [cantidad_disponible_momento] decimal(12,2) NOT NULL,
  [cantidad_faltante] decimal(12,2) NOT NULL,
  [tipo_rotura] nvarchar(20) NOT NULL,
  [fecha_hora] datetime NOT NULL,
  [usuario_id] nvarchar(100) NOT NULL,
  [observaciones] nvarchar(500) NULL,
  [created_at] datetime NULL,
  [updated_at] datetime NULL,
  PRIMARY KEY ([id])
);

CREATE TABLE [Empleado] (
  [EmpleadoId] nvarchar(20) NOT NULL,
  [EmpleadoNombres] nvarchar(100) NOT NULL,
  [EmpleadoApellidos] nvarchar(100) NOT NULL,
  [EmpleadoDni] nvarchar(45) NOT NULL,
  [EmpleadoTelefono] nvarchar(45) NOT NULL,
  [EmpleadoCorreo] nvarchar(150) NOT NULL,
  [EmpleadoSexo] nchar(1) NOT NULL,
  [EmpleadoEstado] nchar(1) NOT NULL,
  [Empleado_cargoId] nvarchar(20) NOT NULL,
  [EmpleadoEliminado] nchar(1) NOT NULL,
  [EmpleadoUsuarioCreacion] nvarchar(100) NOT NULL,
  [EmpleadoHostCreacion] nvarchar(100) NOT NULL,
  [EmpleadoFechaCreacion] datetime NOT NULL,
  [EmpleadoUsuarioModificacion] nvarchar(100) NULL,
  [EmpleadoHostModificacion] nvarchar(100) NULL,
  [EmpleadoFechaModificacion] datetime NULL,
  [EmpleadoUsuarioEliminacion] nvarchar(100) NULL,
  [EmpleadoHostEliminacion] nvarchar(100) NULL,
  [EmpleadoFechaEliminacion] datetime NULL,
  [EmpleadoFechaIngreso] date NULL,
  PRIMARY KEY ([EmpleadoId])
);

CREATE TABLE [Empresa] (
  [Id_Empresa] bigint NOT NULL,
  [EmpresaRuc] nvarchar(11) NOT NULL,
  [EmpresaRazonSocial] nvarchar(255) NOT NULL,
  [EmpresaNombreComercial] nvarchar(255) NULL,
  [EmpresaDireccion] nvarchar(500) NULL,
  [EmpresaDepartamento] nvarchar(100) NULL,
  [EmpresaProvincia] nvarchar(100) NULL,
  [EmpresaDistrito] nvarchar(100) NULL,
  [EmpresaUbigeo] nvarchar(20) NULL,
  [EmpresaTelefono] nvarchar(50) NULL,
  [EmpresaWhatsapp] nvarchar(50) NULL,
  [EmpresaEmail] nvarchar(150) NULL,
  [EmpresaLogo] nvarchar(MAX) NULL,
  [EmpresaMensajeTicket] nvarchar(MAX) NULL,
  [EmpresaEstado] nchar(1) NOT NULL,
  [EmpresaUsuarioCreacion] nvarchar(100) NULL,
  [EmpresaHostCreacion] nvarchar(100) NULL,
  [EmpresaFechaCreacion] datetime NOT NULL,
  [EmpresaUsuarioModificacion] nvarchar(100) NULL,
  [EmpresaHostModificacion] nvarchar(100) NULL,
  [EmpresaFechaModificacion] datetime NULL,
  [EmpresaLatitud] decimal(11,8) NULL,
  [EmpresaLongitud] decimal(11,8) NULL,
  [EmpresaZonaReferencia] nvarchar(100) NULL,
  PRIMARY KEY ([Id_Empresa])
);

CREATE TABLE [Movimiento_producto] (
  [Movimiento_productoId] nvarchar(20) NOT NULL,
  [Movimiento_productoCantidadPresentacion] nvarchar(45) NOT NULL,
  [Movimiento_productoDocumentoOperacionId] nvarchar(45) NOT NULL,
  [Movimiento_productoTipoMovimiento] nchar(1) NOT NULL,
  [Movimiento_productoCostoPrecioUnitario] decimal(10,2) NOT NULL,
  [Movimiento_productoCantidadEntrada] nvarchar(45) NOT NULL,
  [Movimiento_productoCantidadSalida] nvarchar(45) NOT NULL,
  [Movimiento_productoCantidadSaldo] nvarchar(45) NOT NULL,
  [Movimiento_productoFecha_Movimiento] datetime NOT NULL,
  [Movimiento_producto_ProductoId] nvarchar(20) NOT NULL,
  [Movimiento_producto_Detalle_Producto_medida_unidades_medidaId] nvarchar(20) NOT NULL,
  [Movimiento_productoEliminado] nchar(1) NOT NULL,
  [Movimiento_productoUsuarioCreacion] nvarchar(100) NOT NULL,
  [Movimiento_productoHostCreacion] nvarchar(100) NOT NULL,
  [Movimiento_productoFechaCreacion] datetime NOT NULL,
  [Movimiento_productoUsuarioModificacion] nvarchar(100) NULL,
  [Movimiento_productoHostModificacion] nvarchar(100) NULL,
  [Movimiento_productoFechaModificacion] datetime NULL,
  [Movimiento_productoUsuarioEliminacion] nvarchar(100) NULL,
  [Movimiento_productoHostEliminacion] nvarchar(100) NULL,
  [Movimiento_productoFechaEliminacion] datetime NULL,
  [Movimiento_productoSubtipo] nvarchar(30) NULL,
  [Movimiento_productoReferenciaTipo] nvarchar(30) NULL,
  [Movimiento_productoReferenciaId] nvarchar(45) NULL,
  [Movimiento_productoMotivo] nvarchar(500) NULL,
  PRIMARY KEY ([Movimiento_productoId])
);

CREATE TABLE [Notificacion] (
  [NotificacionId] nvarchar(30) NOT NULL,
  [NotificacionTitulo] nvarchar(100) NOT NULL,
  [NotificacionMensaje] nvarchar(255) NOT NULL,
  [NotificacionTipo] nvarchar(30) NOT NULL,
  [NotificacionCategoria] nvarchar(50) NULL,
  [NotificacionReferenciaId] nvarchar(30) NULL,
  [NotificacionDatos] nvarchar(MAX) NULL,
  [NotificacionLeida] nchar(1) NOT NULL,
  [NotificacionSilenciosa] nchar(1) NOT NULL,
  [NotificacionFecha] datetime NOT NULL,
  [NotificacionUsuarioId] nvarchar(20) NULL,
  [NotificacionEliminado] nchar(1) NOT NULL,
  PRIMARY KEY ([NotificacionId])
);

CREATE TABLE [Orden_Compra] (
  [Orden_CompraId] nvarchar(20) NOT NULL,
  [Orden_CompraFecha] datetime NOT NULL,
  [Orden_CompraSubtotal] decimal(10,2) NOT NULL,
  [Orden_CompraIgv] decimal(10,2) NOT NULL,
  [Orden_CompraTotal] decimal(10,2) NOT NULL,
  [Orden_CompraEstado] varchar(30) NOT NULL,
  [Orden_CompraObservacion] nvarchar(250) NULL,
  [Orden_Compra_ProveedorId] nvarchar(20) NULL,
  [Orden_CompraEliminado] nchar(1) NOT NULL,
  [Orden_CompraUsuarioCreacion] nvarchar(100) NOT NULL,
  [Orden_CompraHostCreacion] nvarchar(100) NOT NULL,
  [Orden_CompraFechaCreacion] datetime NOT NULL,
  [Orden_CompraUsuarioModificacion] nvarchar(100) NULL,
  [Orden_CompraHostModificacion] nvarchar(100) NULL,
  [Orden_CompraFechaModificacion] datetime NULL,
  [Orden_CompraUsuarioEliminacion] nvarchar(100) NULL,
  [Orden_CompraHostEliminacion] nvarchar(100) NULL,
  [Orden_CompraFechaEliminacion] datetime NULL,
  [Orden_CompraFechaEstimadaLlegada] datetime NULL,
  [Orden_CompraFechaRecepcionReal] datetime NULL,
  [Orden_CompraMotivoAnulacion] nvarchar(500) NULL,
  [Orden_CompraUsuarioRecepcionId] nvarchar(100) NULL,
  [Orden_CompraCerradaConFaltante] nchar(1) NOT NULL,
  PRIMARY KEY ([Orden_CompraId])
);

CREATE TABLE [Pedido] (
  [PedidoId] nvarchar(20) NOT NULL,
  [PedidoFecha_pedido] datetime NOT NULL,
  [PedidoTotal] decimal(10,2) NOT NULL,
  [PedidoIgv] decimal(10,2) NOT NULL,
  [PedidoUsuarioRegistro] nvarchar(45) NOT NULL,
  [PedidoEstado_pedido] nchar(1) NOT NULL,
  [PedidoAcuerdo_Comercial] nvarchar(45) NOT NULL,
  [Pedido_ClienteId] nvarchar(20) NOT NULL,
  [Pedido_canal_pedidoId] nvarchar(20) NOT NULL,
  [PedidoEliminado] nchar(1) NOT NULL,
  [PedidoUsuarioCreacion] nvarchar(100) NOT NULL,
  [PedidoHostCreacion] nvarchar(100) NOT NULL,
  [PedidoFechaCreacion] datetime NOT NULL,
  [PedidoUsuarioModificacion] nvarchar(100) NULL,
  [PedidoHostModificacion] nvarchar(100) NULL,
  [PedidoFechaModificacion] datetime NULL,
  [PedidoUsuarioEliminacion] nvarchar(100) NULL,
  [PedidoHostEliminacion] nvarchar(100) NULL,
  [PedidoFechaEliminacion] datetime NULL,
  [PedidoMotivoAnulacion] nvarchar(200) NULL,
  [PedidoTieneError] nchar(1) NOT NULL,
  [PedidoTipoError] nvarchar(100) NULL,
  [PedidoOrigenIA] nchar(1) NOT NULL,
  [PedidoEstadoDespacho] nvarchar(30) NULL,
  [PedidoCausaFalloDespacho] nvarchar(50) NULL,
  [PedidoFechaInicioPreparacion] datetime NULL,
  [PedidoFechaDespacho] datetime NULL,
  [PedidoUsuarioDespacho] nvarchar(50) NULL,
  [PedidoOrigenError] nvarchar(30) NULL,
  [PedidoGravedadError] nvarchar(20) NULL,
  [PedidoOrigenDeteccionError] nvarchar(30) NULL,
  [PedidoMomentoDeteccionError] nvarchar(30) NULL,
  [PedidoCostoError] decimal(10,2) NOT NULL,
  [PedidoScoreConfianzaIA] decimal(5,2) NULL,
  [PedidoTiempoCorreccionMin] int NOT NULL,
  [PedidoIdOriginal] nvarchar(20) NULL,
  [PedidoTiempoRegistroSeg] int NOT NULL,
  [PedidoTiempoEfectivoSeg] int NOT NULL,
  [PedidoTiempoSeleccionClienteMs] int NOT NULL,
  [PedidoTiempoCargaProductosMs] int NOT NULL,
  [PedidoTiempoValidacionStockMs] int NOT NULL,
  [PedidoTiempoConfirmacionPagoMs] int NOT NULL,
  [PedidoDispositivo] nvarchar(20) NULL,
  [PedidoTipoCliente] nvarchar(20) NULL,
  [PedidoIntentosCorreccion] int NOT NULL,
  [PedidoFechaEntrega] datetime NULL,
  [PedidoEsDelivery] nchar(1) NOT NULL,
  [PedidoDireccionEntrega] nvarchar(300) NULL,
  [PedidoReferenciaEntrega] nvarchar(200) NULL,
  [PedidoZonaDeliveryId] nvarchar(20) NULL,
  [PedidoCostoDelivery] decimal(10,2) NOT NULL,
  [PedidoLatitudEntrega] decimal(10,7) NULL,
  [PedidoLongitudEntrega] decimal(10,7) NULL,
  [PedidoTieneFaltante] nvarchar(1) NULL,
  [PedidoTotalDespachado] decimal(18,2) NULL,
  [PedidoFechaInicioDespacho] datetime NULL,
  [PedidoFechaFinDespacho] datetime NULL,
  [PedidoObservacionDespacho] nvarchar(500) NULL,
  [PedidoDespachadoPor] nvarchar(100) NULL,
  [PedidoCerradoPor] nvarchar(100) NULL,
  PRIMARY KEY ([PedidoId])
);

CREATE TABLE [pedido_historial_correccion] (
  [id] bigint NOT NULL,
  [pedido_id] nvarchar(20) NOT NULL,
  [campo_modificado] nvarchar(100) NOT NULL,
  [valor_anterior] nvarchar(MAX) NULL,
  [valor_nuevo] nvarchar(MAX) NULL,
  [motivo] nvarchar(200) NULL,
  [usuario] nvarchar(50) NULL,
  [created_at] datetime NULL,
  PRIMARY KEY ([id])
);

CREATE TABLE [Producto] (
  [ProductoId] nvarchar(20) NOT NULL,
  [ProductoNombre] nvarchar(45) NOT NULL,
  [Producto_Categoria_ProductoId] nvarchar(20) NOT NULL,
  [ProductoMarca] nvarchar(45) NOT NULL,
  [ProductoStockActual] nvarchar(45) NOT NULL,
  [ProductoStockMinimo] nvarchar(45) NOT NULL,
  [ProductoStockMaximo] nvarchar(45) NOT NULL,
  [ProductoEstado] nchar(1) NOT NULL,
  [ProductoEliminado] nchar(1) NOT NULL,
  [ProductoUsuarioCreacion] nvarchar(100) NOT NULL,
  [ProductoHostCreacion] nvarchar(100) NOT NULL,
  [ProductoFechaCreacion] datetime NOT NULL,
  [ProductoUsuarioModificacion] nvarchar(100) NULL,
  [ProductoHostModificacion] nvarchar(100) NULL,
  [ProductoFechaModificacion] datetime NULL,
  [ProductoUsuarioEliminacion] nvarchar(100) NULL,
  [ProductoHostEliminacion] nvarchar(100) NULL,
  [ProductoFechaEliminacion] datetime NULL,
  [producto_descripcion] nvarchar(50) NULL,
  [producto_imagen] nvarchar(255) NULL,
  [Producto_producto_ubi_id] nvarchar(20) NULL,
  [ProductoZona] nvarchar(50) NULL,
  [ProductoUbicacion] nvarchar(50) NULL,
  PRIMARY KEY ([ProductoId])
);

CREATE TABLE [Producto_Proveedor] (
  [Producto_Proveedor_ProveedorId] nvarchar(20) NOT NULL,
  [Producto_Proveedor_ProductoId] nvarchar(20) NOT NULL,
  [Producto_ProveedorEliminado] nchar(1) NOT NULL,
  [Producto_ProveedorUsuarioCreacion] nvarchar(100) NOT NULL,
  [Producto_ProveedorHostCreacion] nvarchar(100) NOT NULL,
  [Producto_ProveedorFechaCreacion] datetime NOT NULL,
  [Producto_ProveedorUsuarioModificacion] nvarchar(100) NULL,
  [Producto_ProveedorHostModificacion] nvarchar(100) NULL,
  [Producto_ProveedorFechaModificacion] datetime NULL,
  [Producto_ProveedorUsuarioEliminacion] nvarchar(100) NULL,
  [Producto_ProveedorHostEliminacion] nvarchar(100) NULL,
  [Producto_ProveedorFechaEliminacion] datetime NULL,
  PRIMARY KEY ([Producto_Proveedor_ProveedorId], [Producto_Proveedor_ProductoId])
);

CREATE TABLE [producto_ubi] (
  [producto_ubi_id] nvarchar(20) NOT NULL,
  [producto_ubi_descripcion] nvarchar(100) NOT NULL,
  [producto_ubi_estado] nchar(1) NOT NULL,
  [producto_ubi_observacion] nvarchar(255) NULL,
  [producto_ubi_Eliminado] nchar(1) NOT NULL,
  [producto_ubi_UsuarioCreacion] nvarchar(100) NOT NULL,
  [producto_ubi_HostCreacion] nvarchar(100) NOT NULL,
  [producto_ubi_FechaCreacion] datetime NOT NULL,
  [producto_ubi_UsuarioModificacion] nvarchar(100) NULL,
  [producto_ubi_HostModificacion] nvarchar(100) NULL,
  [producto_ubi_FechaModificacion] datetime NULL,
  [producto_ubi_UsuarioEliminacion] nvarchar(100) NULL,
  [producto_ubi_HostEliminacion] nvarchar(100) NULL,
  [producto_ubi_FechaEliminacion] datetime NULL,
  PRIMARY KEY ([producto_ubi_id])
);

CREATE TABLE [Proveedor] (
  [ProveedorId] nvarchar(20) NOT NULL,
  [ProveedorRuc] nvarchar(45) NOT NULL,
  [ProveedorRazonSocial] nvarchar(45) NOT NULL,
  [ProveedorTipoContribuyente] nvarchar(45) NOT NULL,
  [ProveedorEstado] nchar(1) NOT NULL,
  [ProveedorActividadEconomica] nvarchar(45) NOT NULL,
  [ProveedorTelefono] nvarchar(45) NOT NULL,
  [ProveedorEliminado] nchar(1) NOT NULL,
  [ProveedorUsuarioCreacion] nvarchar(100) NOT NULL,
  [ProveedorHostCreacion] nvarchar(100) NOT NULL,
  [ProveedorFechaCreacion] datetime NOT NULL,
  [ProveedorUsuarioModificacion] nvarchar(100) NULL,
  [ProveedorHostModificacion] nvarchar(100) NULL,
  [ProveedorFechaModificacion] datetime NULL,
  [ProveedorUsuarioEliminacion] nvarchar(100) NULL,
  [ProveedorHostEliminacion] nvarchar(100) NULL,
  [ProveedorFechaEliminacion] datetime NULL,
  PRIMARY KEY ([ProveedorId])
);

CREATE TABLE [Rol_User] (
  [Rol_UserId] nvarchar(20) NOT NULL,
  [Rol_UserEmpleadocol] nvarchar(45) NOT NULL,
  [Rol_UserRol_Usercol] nvarchar(45) NOT NULL,
  [Rol_UserEliminado] nchar(1) NOT NULL,
  [Rol_UserUsuarioCreacion] nvarchar(100) NOT NULL,
  [Rol_UserHostCreacion] nvarchar(100) NOT NULL,
  [Rol_UserFechaCreacion] datetime NOT NULL,
  [Rol_UserUsuarioModificacion] nvarchar(100) NULL,
  [Rol_UserHostModificacion] nvarchar(100) NULL,
  [Rol_UserFechaModificacion] datetime NULL,
  [Rol_UserUsuarioEliminacion] nvarchar(100) NULL,
  [Rol_UserHostEliminacion] nvarchar(100) NULL,
  [Rol_UserFechaEliminacion] datetime NULL,
  PRIMARY KEY ([Rol_UserId])
);

CREATE TABLE [unidades_medida] (
  [unidades_medidaId] nvarchar(20) NOT NULL,
  [unidades_medidaDescripcionUnidades] nvarchar(45) NOT NULL,
  [unidades_medidaEstadoUnidades] nchar(1) NOT NULL,
  [unidades_medidaEliminado] nchar(1) NOT NULL,
  [unidades_medidaUsuarioCreacion] nvarchar(100) NOT NULL,
  [unidades_medidaHostCreacion] nvarchar(100) NOT NULL,
  [unidades_medidaFechaCreacion] datetime NOT NULL,
  [unidades_medidaUsuarioModificacion] nvarchar(100) NULL,
  [unidades_medidaHostModificacion] nvarchar(100) NULL,
  [unidades_medidaFechaModificacion] datetime NULL,
  [unidades_medidaUsuarioEliminacion] nvarchar(100) NULL,
  [unidades_medidaHostEliminacion] nvarchar(100) NULL,
  [unidades_medidaFechaEliminacion] datetime NULL,
  [unidades_medidaAbreviatura] nvarchar(10) NULL,
  [unidades_medidaEsBase] bit NOT NULL,
  PRIMARY KEY ([unidades_medidaId])
);

CREATE TABLE [Usuario] (
  [UsuarioId] nvarchar(20) NOT NULL,
  [UsuarioUserName] nvarchar(150) NOT NULL,
  [UsuarioPassword] nvarchar(255) NOT NULL,
  [Usuario_Rol_UserId] nvarchar(20) NOT NULL,
  [Usuario_EmpleadoId] nvarchar(20) NOT NULL,
  [UsuarioEliminado] nchar(1) NOT NULL,
  [UsuarioUsuarioCreacion] nvarchar(100) NOT NULL,
  [UsuarioHostCreacion] nvarchar(100) NOT NULL,
  [UsuarioFechaCreacion] datetime NOT NULL,
  [UsuarioUsuarioModificacion] nvarchar(100) NULL,
  [UsuarioHostModificacion] nvarchar(100) NULL,
  [UsuarioFechaModificacion] datetime NULL,
  [UsuarioUsuarioEliminacion] nvarchar(100) NULL,
  [UsuarioHostEliminacion] nvarchar(100) NULL,
  [UsuarioFechaEliminacion] datetime NULL,
  PRIMARY KEY ([UsuarioId])
);

CREATE TABLE [Zona_Delivery] (
  [Zona_DeliveryId] nvarchar(20) NOT NULL,
  [Zona_DeliveryNombre] nvarchar(100) NOT NULL,
  [Zona_DeliveryTarifa] decimal(10,2) NOT NULL,
  [Zona_DeliveryPoligonoGeoJSON] nvarchar(MAX) NOT NULL,
  [Zona_DeliveryEstado] nchar(1) NOT NULL,
  [Zona_DeliveryUsuarioCreacion] nvarchar(100) NOT NULL,
  [Zona_DeliveryHostCreacion] nvarchar(50) NOT NULL,
  [Zona_DeliveryFechaCreacion] datetime NOT NULL,
  [Zona_DeliveryUsuarioModificacion] nvarchar(100) NULL,
  [Zona_DeliveryHostModificacion] nvarchar(50) NULL,
  [Zona_DeliveryFechaModificacion] datetime NULL,
  PRIMARY KEY ([Zona_DeliveryId])
);

-- Relaciones (FK físicas del motor)
ALTER TABLE [Ajuste_inventario] ADD CONSTRAINT [Ajuste_inventario_ProductoFK] FOREIGN KEY ([Ajuste_inventario_ProductoId]) REFERENCES [Producto] ([ProductoId]);
ALTER TABLE [auditoria_roturas_stock] ADD CONSTRAINT [auditoria_roturas_stock_pedido_id_foreign] FOREIGN KEY ([pedido_id]) REFERENCES [Pedido] ([PedidoId]);
ALTER TABLE [Detalle_Orden_Compra] ADD CONSTRAINT [FK_Detalle_Orden_Compra_Orden] FOREIGN KEY ([Detalle_Orden_Compra_Orden_CompraId]) REFERENCES [Orden_Compra] ([Orden_CompraId]) ON DELETE CASCADE;
ALTER TABLE [Detalle_Pedido_Productos] ADD CONSTRAINT [FK_Detalle_Pedido_Pedido] FOREIGN KEY ([Detalle_Pedido_Productos_PedidoId]) REFERENCES [Pedido] ([PedidoId]) ON DELETE CASCADE;
ALTER TABLE [Detalle_Pedido_Productos] ADD CONSTRAINT [FK_Detalle_Pedido_Producto] FOREIGN KEY ([Detalle_Pedido_Productos_ProductoId]) REFERENCES [Producto] ([ProductoId]);
ALTER TABLE [Detalle_Producto_medida] ADD CONSTRAINT [Detalle_Producto_medida_ProductoFK] FOREIGN KEY ([Detalle_Producto_medida_ProductoId]) REFERENCES [Producto] ([ProductoId]);
ALTER TABLE [Detalle_Producto_medida] ADD CONSTRAINT [Detalle_Producto_medida_unidades_medidaFK] FOREIGN KEY ([Detalle_Producto_medida_unidades_medidaId]) REFERENCES [unidades_medida] ([unidades_medidaId]);
ALTER TABLE [detalle_rotura_stock] ADD CONSTRAINT [FK_rotura_stock_pedido] FOREIGN KEY ([pedido_id]) REFERENCES [Pedido] ([PedidoId]);
ALTER TABLE [Empleado] ADD CONSTRAINT [Empleado_cargoFK] FOREIGN KEY ([Empleado_cargoId]) REFERENCES [cargo] ([cargoId]);
ALTER TABLE [Movimiento_producto] ADD CONSTRAINT [Movimiento_producto_ProductoFK] FOREIGN KEY ([Movimiento_producto_ProductoId]) REFERENCES [Producto] ([ProductoId]);
ALTER TABLE [Orden_Compra] ADD CONSTRAINT [FK_Orden_Compra_Proveedor] FOREIGN KEY ([Orden_Compra_ProveedorId]) REFERENCES [Proveedor] ([ProveedorId]);
ALTER TABLE [Pedido] ADD CONSTRAINT [FK_Pedido_ZonaDelivery] FOREIGN KEY ([PedidoZonaDeliveryId]) REFERENCES [Zona_Delivery] ([Zona_DeliveryId]);
ALTER TABLE [Pedido] ADD CONSTRAINT [Pedido_canal_pedidoFK] FOREIGN KEY ([Pedido_canal_pedidoId]) REFERENCES [Canal_pedido] ([Canal_pedidoId]);
ALTER TABLE [Pedido] ADD CONSTRAINT [Pedido_ClienteFK] FOREIGN KEY ([Pedido_ClienteId]) REFERENCES [Cliente] ([ClienteId]);
ALTER TABLE [pedido_historial_correccion] ADD CONSTRAINT [pedido_historial_correccion_pedido_id_foreign] FOREIGN KEY ([pedido_id]) REFERENCES [Pedido] ([PedidoId]);
ALTER TABLE [Producto] ADD CONSTRAINT [Producto_Categoria_ProductoFK] FOREIGN KEY ([Producto_Categoria_ProductoId]) REFERENCES [Categoria_Producto] ([Categoria_ProductoId]);
ALTER TABLE [Producto] ADD CONSTRAINT [Producto_producto_ubiFK] FOREIGN KEY ([Producto_producto_ubi_id]) REFERENCES [producto_ubi] ([producto_ubi_id]);
ALTER TABLE [Producto_Proveedor] ADD CONSTRAINT [Producto_Proveedor_ProductoFK] FOREIGN KEY ([Producto_Proveedor_ProductoId]) REFERENCES [Producto] ([ProductoId]);
ALTER TABLE [Producto_Proveedor] ADD CONSTRAINT [Producto_Proveedor_ProveedorFK] FOREIGN KEY ([Producto_Proveedor_ProveedorId]) REFERENCES [Proveedor] ([ProveedorId]);
ALTER TABLE [Usuario] ADD CONSTRAINT [Usuario_EmpleadoFK] FOREIGN KEY ([Usuario_EmpleadoId]) REFERENCES [Empleado] ([EmpleadoId]);
ALTER TABLE [Usuario] ADD CONSTRAINT [Usuario_Rol_UserFK] FOREIGN KEY ([Usuario_Rol_UserId]) REFERENCES [Rol_User] ([Rol_UserId]);
