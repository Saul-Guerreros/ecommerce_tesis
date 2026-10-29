import { INavData } from '@coreui/angular';
import { environment } from '../../../environments/environment';

export const navItems: INavData[] = [
  {
    name: 'Dashboard',
    url: '/dashboard',
    iconComponent: { name: 'cil-speedometer' },
  },
  // ==========================================
  // MÉTRICAS Y REPORTES
  // ==========================================
  {
    title: true,
    name: 'Métricas y Reportes'
  },
  {
    name: 'Reportes',
    url: '/reportes',
    iconComponent: { name: 'cilChartPie' },
    children: [
      { name: 'Inicio', url: '/reportes', linkProps: { routerLinkActiveOptions: { exact: true } } },
      { name: 'Ventas', url: '/reportes/ventas' },
      { name: 'Inventario', url: '/reportes/inventario' },
      { name: 'Compras', url: '/reportes/compras' },
      { name: 'Servicio técnico', url: '/reportes/servicio' },
      { name: 'Clientes', url: '/reportes/clientes' },
      { name: 'Promociones', url: '/reportes/promociones' },
      { name: 'Asistente virtual', url: '/reportes/chatbot' },
      { name: 'Comportamiento de clientes', url: '/reportes/comportamiento' },
    ]
  },

  // ==========================================
  // 1. PUBLICACIÓN WEB
  // ==========================================
  {
    title: true,
    name: 'Publicación Web'
  },
  {
    name: 'Ir a la Tienda',
    url: environment.URL_FRONTEND,
    iconComponent: { name: 'cil-external-link' },
    attributes: { target: '_blank' }
  },
  {
    name: 'Publicaciones',
    url: '/publicaciones',
    iconComponent: { name: 'cilBasket' },
    children: [
      { name: 'Lista de publicaciones', url: '/publicaciones' },
      { name: 'Categorías', url: '/categories' },
      { name: 'Atributos', url: '/atributos/list' },
	  { name: 'Plantillas de Ficha Técnica', url: '/plantillas-ficha' },
    { name: 'Atributos', url: '/atributos/list' },
    { name: 'Marcas', url: '/brands' },
    ]
  },
  {
    name: 'Sliders',
    url: '/sliders/list',
    iconComponent: { name: 'cil-image' }
  },
  {
    name: 'Promociones',
    iconComponent: { name: 'cil-gift' },
    children: [
      { name: 'Cupones', url: '/cupones/list' },
      { name: 'Campañas de Descuento', url: '/discounts/list' }
    ]
  },
  {
    name: 'Asistente Virtual',
    iconComponent: { name: 'cil-comment-square' },
    children: [
      { name: 'Información de la tienda', url: '/chatbot/informacion' }
    ]
  },

  // ==========================================
  // 2. VENTAS
  // ==========================================
  {
    title: true,
    name: 'Ventas'
  },
  {
    name: 'Pedidos',
    url: '/pedidos',
    iconComponent: { name: 'cil-notes' }
  },
  {
    name: 'Facturas de Venta',
    url: '/facturas-venta',
    iconComponent: { name: 'cil-description' }
  },
  
  // ==========================================
  // SERVICIO TÉCNICO
  // ==========================================
  {
    title: true,
    name: 'Servicio Técnico'
  },
  {
    name: 'Panel general',
    url: '/servicios/panel',
    iconComponent: { name: 'cil-speedometer' }
  },
  {
    name: 'Bandeja de solicitudes',
    url: '/servicios/solicitudes',
    iconComponent: { name: 'cil-inbox' }
  },
  {
    name: 'Mis órdenes',
    url: '/servicios/mis-ordenes',
    iconComponent: { name: 'cil-task' }
  },
  {
    name: 'Validar presupuestos',
    url: '/servicios/presupuestos',
    iconComponent: { name: 'cil-calculator' }
  },
  {
    name: 'Cobros de servicios',
    url: '/servicios/cobros',
    iconComponent: { name: 'cil-dollar' }
  },
  {
    name: 'Catálogos de servicio',
    url: '/servicios',
    iconComponent: { name: 'cil-settings' },
    children: [
      { name: 'Tipos de dispositivo', url: '/servicios/tipos-dispositivo', icon: 'nav-icon-bullet' },
      { name: 'Fallas', url: '/servicios/fallas', icon: 'nav-icon-bullet' },
      { name: 'Trabajos', url: '/servicios/trabajos', icon: 'nav-icon-bullet' },
    ]
  },

  // ==========================================
  // 3. COMPRAS
  // ==========================================
  {
    title: true,
    name: 'Compras'
  },
  {
    name: 'Proveedores',
    iconComponent: { name: 'cil-cart' },
    children: [
      { name: 'Lista de Proveedores', url: '/proveedores' },
      { name: 'Tipos de Proveedor', url: '/tipos-proveedor' }
    ]
  },
  {
    name: 'Facturas de Compra',
    url: '/facturas-compra',
    iconComponent: { name: 'cil-description' }
  },
  {
    name: 'Cuentas por Pagar',
    url: '/cuentas-por-pagar',
    iconComponent: { name: 'cil-dollar' }
  },
  {
    name: 'Libro de Compras',
    url: '/libro-compra',
    iconComponent: { name: 'cil-spreadsheet' }
  },

  // ==========================================
  // 4. INVENTARIO
  // ==========================================
  {
    title: true,
    name: 'Inventario'
  },
  {
    name: 'Productos',
    url: '/inventario/productos',
    iconComponent: { name: 'cilBasket' },
    children: [
      { name: 'Lista de productos', url: '/inventario/productos' },
      { name: 'Unidades de Medida', url: '/unidades-medida' }
    ]
  },
  {
    name: 'Configuración de Margen',
    url: '/configuracion-precio',
    iconComponent: { name: 'cil-settings' }
  },
  {
    name: 'Gestión de Precios',
    url: '/precios',
    iconComponent: { name: 'cil-dollar' }
  },
  {
    name: 'Ajustes de Inventario',
    iconComponent: { name: 'cil-swap-vertical' },
    children: [
      { name: 'Lista de ajustes', url: '/inventario/ajustes' },
      { name: 'Tipos de ajuste', url: '/inventario/tipos-ajuste' }
    ]
  },
  {
    name: 'Movimientos de Stock (Kardex)',
    url: '/inventario/kardex',
    iconComponent: { name: 'cil-history' }
  },
  {
    name: 'Stock Actual',
    url: '/inventario/stock-actual',
    iconComponent: { name: 'cil-layers' }
  },

  // ==========================================
  // 5. AJUSTES DE LA TIENDA
  // ==========================================
  {
    title: true,
    name: 'Ajustes de la Tienda'
  },
  {
    name: 'Gestión de Cuotas',
    url: '/gestion-cuotas',
    iconComponent: { name: 'cil-calculator' }
  },
  {
    name: 'Alertas de Stock',
    url: '/configuracion-tienda',
    iconComponent: { name: 'cil-bell' }
  },
  {
    name: 'Métodos de Pago',
    url: '/configuracion-pago',
    iconComponent: { name: 'cil-credit-card' }
  },
  {
    name: 'Configuración de Envíos',
    iconComponent: { name: 'cil-truck' },
    children: [
      { name: 'Configuración de Envío', url: '/configuracion-envio' },
      { name: 'Tarifas de Envío', url: '/tarifas-envio' }
    ]
  },

  // ==========================================
  // 6. PERSONAS
  // ==========================================
  {
    title: true,
    name: 'Personas'
  },
  {
    name: 'Usuarios',
    iconComponent: { name: 'cil-people' },
    children: [
      {
        name: 'Clientes',
        iconComponent: { name: 'cil-user' },
        children: [
          { name: 'Lista de Clientes', url: '/clientes' },
          { name: 'Tipos de Cliente', url: '/tipos-cliente' }
        ]
      },
      {
        name: 'Funcionarios',
        iconComponent: { name: 'cil-people' },
        children: [
          { name: 'Lista de Funcionarios', url: '/funcionarios' },
          { name: 'Cargos', url: '/cargos' }
        ]
      }
    ]
  },
  {
    name: 'Tipos de Documento',
    url: '/tipos-documento',
    iconComponent: { name: 'cil-description' }
  },
  

  // ==========================================
  // 7. SEGURIDAD Y ACCESO
  // ==========================================
  {
    title: true,
    name: 'Seguridad y Acceso'
  },
  {
    name: 'Roles',
    url: '/roles',
    iconComponent: { name: 'cil-shield-alt' }
  },
  {
    name: 'Permisos',
    url: '/permisos',
    iconComponent: { name: 'cil-check-circle' }
  },
  {
    name: 'Auditoría',
    iconComponent: { name: 'cil-history' },
    children: [
      { name: 'Historial de Contraseñas', url: '/seguridad/historial-password' },
      { name: 'Sesiones Activas', url: '/seguridad/sesiones' },
      { name: 'Intentos de Sesión', url: '/seguridad/intentos-sesion' },
      { name: 'Tokens de Verificación', url: '/seguridad/tokens' }
    ]
  },

  // ==========================================
  // 8. CONFIGURACIÓN
  // ==========================================
  {
    title: true,
    name: 'Configuración'
  },
  {
    name: 'Empresa',
    iconComponent: { name: 'cil-building' },
    children: [
      { name: 'Datos de la Empresa', url: '/empresas' },
      { name: 'Sucursales', url: '/sucursales' },
      { name: 'Depósitos', url: '/depositos' }
    ]
  },
  {
    name: 'Comprobantes',
    iconComponent: { name: 'cil-spreadsheet' },
    children: [
      { name: 'Timbrados', url: '/timbrados' },
      { name: 'Tipos de comprobante', url: '/tipos-comprobante' },
      { name: 'Tipos de IVA', url: '/tipos-iva' }
    ]
  },

  // --- Sub-bloque dentro de Configuración ---
  {
    title: true,
    name: 'Catálogos Generales'
  },
  {
    name: 'Ubicaciones',
    iconComponent: { name: 'cil-map' },
    children: [
      { name: 'Departamentos', url: '/departamentos' },
      { name: 'Ciudades', url: '/ciudades' }
    ]
  },



/*
  {
    title: true,
    name: 'Theme'
  },
  {
    name: 'Colors',
    url: '/theme/colors',
    iconComponent: { name: 'cil-drop' }
  },
  {
    name: 'Typography',
    url: '/theme/typography',
    linkProps: { fragment: 'headings' },
    iconComponent: { name: 'cil-pencil' }
  },
  {
    name: 'Components',
    title: true
  },
  {
    name: 'Base',
    url: '/base',
    iconComponent: { name: 'cil-puzzle' },
    children: [
      {
        name: 'Accordion',
        url: '/base/accordion',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Breadcrumbs',
        url: '/base/breadcrumbs',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Calendar',
        url: 'https://coreui.io/angular/docs/components/calendar/',
        icon: 'nav-icon-bullet',
        badge: {
          color: 'danger',
          text: 'PRO'
        },
        attributes: { target: '_blank' }
      },
      {
        name: 'Cards',
        url: '/base/cards',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Carousel',
        url: '/base/carousel',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Collapse',
        url: '/base/collapse',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'List Group',
        url: '/base/list-group',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Navs & Tabs',
        url: '/base/navs',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Pagination',
        url: '/base/pagination',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Placeholder',
        url: '/base/placeholder',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Smart Table',
        url: 'https://coreui.io/angular/docs/components/smart-table/',
        icon: 'nav-icon-bullet',
        badge: {
          color: 'danger',
          text: 'PRO'
        },
        attributes: { target: '_blank' }
      },
      {
        name: 'Smart Pagination',
        url: 'https://coreui.io/angular/docs/components/smart-pagination/',
        icon: 'nav-icon-bullet',
        badge: {
          color: 'danger',
          text: 'PRO'
        },
        attributes: { target: '_blank' }
      },
      {
        name: 'Popovers',
        url: '/base/popovers',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Progress',
        url: '/base/progress',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Spinners',
        url: '/base/spinners',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Tables',
        url: '/base/tables',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Tabs',
        url: '/base/tabs',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Tooltips',
        url: '/base/tooltips',
        icon: 'nav-icon-bullet'
      }
    ]
  },
  {
    name: 'Buttons',
    url: '/buttons',
    iconComponent: { name: 'cil-cursor' },
    children: [
      {
        name: 'Buttons',
        url: '/buttons/buttons',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Button groups',
        url: '/buttons/button-groups',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Dropdowns',
        url: '/buttons/dropdowns',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Loading Button',
        url: 'https://coreui.io/angular/docs/components/loading-button/',
        icon: 'nav-icon-bullet',
        badge: {
          color: 'danger',
          text: 'PRO'
        },
        attributes: { target: '_blank' }
      }
    ]
  },
  {
    name: 'Forms',
    url: '/forms',
    iconComponent: { name: 'cil-notes' },
    children: [
      {
        name: 'Autocomplete',
        url: 'https://coreui.io/angular/docs/forms/autocomplete/',
        icon: 'nav-icon-bullet',
        badge: {
          color: 'danger',
          text: 'PRO'
        },
        attributes: { target: '_blank' }
      },
      {
        name: 'Form Control',
        url: '/forms/form-control',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Checks & Radios',
        url: '/forms/checks-radios',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Date Picker',
        url: 'https://coreui.io/angular/docs/forms/date-picker/',
        icon: 'nav-icon-bullet',
        badge: {
          color: 'danger',
          text: 'PRO'
        },
        attributes: { target: '_blank' }
      },
      {
        name: 'Date Range Picker',
        url: 'https://coreui.io/angular/docs/forms/date-range-picker/',
        icon: 'nav-icon-bullet',
        badge: {
          color: 'danger',
          text: 'PRO'
        },
        attributes: { target: '_blank' }
      },
      {
        name: 'Floating Labels',
        url: '/forms/floating-labels',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Input Group',
        url: '/forms/input-group',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Multi Select',
        url: 'https://coreui.io/angular/docs/forms/multi-select/',
        icon: 'nav-icon-bullet',
        badge: {
          color: 'danger',
          text: 'PRO'
        },
        attributes: { target: '_blank' }
      },
      {
        name: 'OTP Input',
        url: 'https://coreui.io/angular/docs/forms/otp/',
        icon: 'nav-icon-bullet',
        badge: {
          color: 'danger',
          text: 'PRO'
        },
        attributes: { target: '_blank' }
      },
      {
        name: 'Password Input',
        url: 'https://coreui.io/angular/docs/forms/password-input/',
        icon: 'nav-icon-bullet',
        badge: {
          color: 'danger',
          text: 'PRO'
        },
        attributes: { target: '_blank' }
      },
      {
        name: 'Range',
        url: '/forms/range',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Range Slider',
        url: 'https://coreui.io/angular/docs/forms/range-slider/',
        icon: 'nav-icon-bullet',
        badge: {
          color: 'danger',
          text: 'PRO'
        },
        attributes: { target: '_blank' }
      },
      {
        name: 'Rating',
        url: 'https://coreui.io/angular/docs/forms/rating/',
        icon: 'nav-icon-bullet',
        badge: {
          color: 'danger',
          text: 'PRO'
        },
        attributes: { target: '_blank' }
      },
      {
        name: 'Select',
        url: '/forms/select',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Stepper',
        url: 'https://coreui.io/angular/docs/forms/stepper/',
        icon: 'nav-icon-bullet',
        badge: {
          color: 'danger',
          text: 'PRO'
        },
        attributes: { target: '_blank' }
      },
      {
        name: 'Time Picker',
        url: 'https://coreui.io/angular/docs/forms/time-picker/',
        icon: 'nav-icon-bullet',
        badge: {
          color: 'danger',
          text: 'PRO'
        },
        attributes: { target: '_blank' }
      },
      {
        name: 'Layout',
        url: '/forms/layout',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Validation',
        url: '/forms/validation',
        icon: 'nav-icon-bullet'
      }
    ]
  },
  /*
  {
    name: 'Charts',
    iconComponent: { name: 'cil-chart-pie' },
    url: '/charts'
  },
  {
    name: 'Icons',
    iconComponent: { name: 'cil-star' },
    url: '/icons',
    children: [
      {
        name: 'CoreUI Free',
        url: '/icons/coreui-icons',
        icon: 'nav-icon-bullet',
        badge: {
          color: 'success',
          text: 'FREE'
        }
      },
      {
        name: 'CoreUI Flags',
        url: '/icons/flags',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'CoreUI Brands',
        url: '/icons/brands',
        icon: 'nav-icon-bullet'
      }
    ]
  },
  {
    name: 'Notifications',
    url: '/notifications',
    iconComponent: { name: 'cil-bell' },
    children: [
      {
        name: 'Alerts',
        url: '/notifications/alerts',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Badges',
        url: '/notifications/badges',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Modal',
        url: '/notifications/modal',
        icon: 'nav-icon-bullet'
      },
      {
        name: 'Toast',
        url: '/notifications/toasts',
        icon: 'nav-icon-bullet'
      }
    ]
  },
  {
    name: 'Widgets',
    url: '/widgets',
    iconComponent: { name: 'cil-calculator' },
    badge: {
      color: 'info',
      text: 'NEW'
    }
  },

  {
    title: true,
    name: 'Extras'
  },
 // */ 
  // {
    // name: 'Pages',
    // url: '/login',
    // iconComponent: { name: 'cil-star' },
    // children: [
      // {
        // name: 'Login',
        // url: '/login',
        // icon: 'nav-icon-bullet'
      // },
      // {
        // name: 'Register',
        // url: '/register',
        // icon: 'nav-icon-bullet'
      // },
      // // {
        // // name: 'Error 404',
        // // url: '/404',
        // // icon: 'nav-icon-bullet'
      // // },
      // // {
        // // name: 'Error 500',
        // // url: '/500',
        // // icon: 'nav-icon-bullet'
      // // }
    // ]
  // },
  // {
    // title: true,
    // name: 'Links',
    // class: 'mt-auto'
  // },

  // {
    // name: 'Docs',
    // url: 'https://coreui.io/angular/docs/',
    // iconComponent: { name: 'cil-description' },
    // attributes: { target: '_blank' }
  // }


];
