import { Routes } from '@angular/router';
import { authGuard } from './auth/guards/auth.guard';
import { permissionGuard } from './auth/guards/permission.guard';

export const routes: Routes = [
  {
    path: '',
    redirectTo: 'dashboard',
    pathMatch: 'full'
  },
  {
    path: '',
    loadComponent: () => import('./layout').then(m => m.DefaultLayoutComponent),
    canActivate: [authGuard],

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
         canActivate: [permissionGuard('atributos.ver')],
      },
      {
        path: 'usuarios',
        loadChildren: () => import('./views/usuarios/usuarios.routes').then((m) => m.USUARIOS_ROUTES),
        // Solo Administrador (Vendedor no tiene 'usuarios.ver', ver RolPermisoSeeder).
        canActivate: [permissionGuard('usuarios.ver')],
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
    path: 'forgot-password',
    loadComponent: () => import('./views/pages/forgot-password/forgot-password.component').then(m => m.ForgotPasswordComponent),
    data: {
      title: 'Forgot Password'
    }
  },
  {
    path: 'reset-password',
    loadComponent: () => import('./views/pages/reset-password/reset-password.component').then(m => m.ResetPasswordComponent),
    data: {
      title: 'Reset Password'
    }
  },
  {
    path: 'register',
    loadComponent: () => import('./views/pages/register/register.component').then(m => m.RegisterComponent),
    // No es auto-registro publico: crea un usuario funcionario (Admin/Vendedor),
    // por eso exige login + el mismo permiso que el modal "+ Nuevo Usuario".
    canActivate: [authGuard, permissionGuard('usuarios.gestionar')],
    data: {
      title: 'Register Page'
    }
  },
  { path: '**', redirectTo: 'dashboard' }
];
