import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: '',
    redirectTo: 'dashboard',
    pathMatch: 'full'
  },
  {
    path: '',
    loadComponent: () => import('./layout').then(m => m.DefaultLayoutComponent),
    

    data: {
      title: 'Home'
    },

    children: [
      {
        path: 'dashboard',
        loadChildren: () => import('./views/dashboard/routes').then((m) => m.routes)
      },
	    

      {
      path: 'productos',
      loadChildren: () => import('./views/productos/productos.routes').then((m) => m.routes),
      data: { title: 'Productos' },
     },
      {
      path: 'categories',
      loadChildren: () => import('./modules/categories/categories.module').then(m => m.CategoriesModule),
      data: { title: 'Categorías' },
      },
      {
      path: 'brands',
      loadChildren: () => import('./modules/brands/brands.module').then(m => m.BrandsModule),
      data: { title: 'Marcas' },
      },
      {
        path: 'atributos',
        loadChildren: () => import('./views/atributos/atributos.routes').then((m) => m.ATRIBUTOS_ROUTES),
      },
      {
        path: 'sliders',
        loadChildren: () => import('./views/sliders/sliders.routes').then((m) => m.SLIDERS_ROUTES),
      },
      {
        path: 'cupones',
        loadChildren: () => import('./views/cupones/routes').then((m) => m.routes),
      },
      
      {
        path: 'empresas',
        loadChildren: () => import('./modules/Configuracion/empresas.module').then(m => m.EmpresasModule),
        data: { title: 'Empresa' },
      },


      {
        path: 'sucursales',
        loadChildren: () => import('./modules/sucursales/sucursales.module').then(m => m.SucursalesModule),
        data: { title: 'Sucursales' },
      },

      {
        path: 'ciudades',
        loadChildren: () => import('./modules/ciudades/ciudades.module').then(m => m.CiudadesModule),
        data: { title: 'Ciudades' },
      },

      {
        path: 'departamentos',
        loadChildren: () => import('./modules/departamentos/departamentos.module').then(m => m.DepartamentosModule),
        data: { title: 'Departamentos' },
      },
      
      {
        path: 'depositos',
        loadChildren: () => import('./modules/depositos/depositos.module').then(m => m.DepositosModule),
        data: { title: 'Depósitos' },
      },

      {
        path: 'timbrados',
        loadChildren: () => import('./modules/timbrados/timbrados.module').then(m => m.TimbradosModule),
        data: { title: 'Timbrados' },
      },

      {
        path: 'tipos-comprobante',
        loadChildren: () => import('./modules/tipos-comprobante/tipos-comprobante.module').then(m => m.TiposComprobanteModule),
        data: { title: 'Tipos de comprobante' },
      },

      {
        path: 'tipos-iva',
        loadChildren: () => import('./modules/tipos-iva/tipos-iva.module').then(m => m.TiposIvaModule),
        data: { title: 'Tipos de IVA' },
      },
      
      {
        path: 'tipos-proveedor',
        loadChildren: () => import('./modules/tipos-proveedor/tipos-proveedor.module').then(m => m.TiposProveedorModule),
        data: { title: 'Tipos de proveedor' },
      },

	  {


		path: 'discounts',
		loadChildren: () => import('./views/discounts/routes').then((m) => m.routes),
	   },
      {
        path: 'theme',
        loadChildren: () => import('./views/theme/routes').then((m) => m.routes)
      },
      {
        path: 'base',
        loadChildren: () => import('./views/base/routes').then((m) => m.routes)
      },
      {
        path: 'buttons',
        loadChildren: () => import('./views/buttons/routes').then((m) => m.routes)
      },
      {
        path: 'forms',
        loadChildren: () => import('./views/forms/routes').then((m) => m.routes)
      },
      {
        path: 'icons',
        loadChildren: () => import('./views/icons/routes').then((m) => m.routes)
      },
      {
        path: 'notifications',
        loadChildren: () => import('./views/notifications/routes').then((m) => m.routes)
      },
      {
        path: 'widgets',
        loadChildren: () => import('./views/widgets/routes').then((m) => m.routes)
      },
      {
        path: 'charts',
        loadChildren: () => import('./views/charts/routes').then((m) => m.routes)
      },
      {
        path: 'pages',
        loadChildren: () => import('./views/pages/routes').then((m) => m.routes)
      }
    ]
  },
  {
    path: '404',
    loadComponent: () => import('./views/pages/page404/page404.component').then(m => m.Page404Component),
    data: {
      title: 'Page 404'
    }
  },
  {
    path: '500',
    loadComponent: () => import('./views/pages/page500/page500.component').then(m => m.Page500Component),
    data: {
      title: 'Page 500'
    }
  },
  {
    path: 'login',
    loadComponent: () => import('./views/pages/login/login.component').then(m => m.LoginComponent),
    data: {
      title: 'Login Page'
    }
  },
  {
    path: 'register',
    loadComponent: () => import('./views/pages/register/register.component').then(m => m.RegisterComponent),
    data: {
      title: 'Register Page'
    }
  },
  { path: '**', redirectTo: 'dashboard' }
];
