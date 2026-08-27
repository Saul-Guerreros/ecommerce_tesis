import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: '',
    data: {
      title: 'Campañas de Descuento',
    },
    children: [
      {
        path: '',
        redirectTo: 'list',
        pathMatch: 'full',
      },
      {
        path: 'list',
        loadComponent: () =>
          import('./list-discounts/list-discounts.component').then((m) => m.ListDiscountsComponent),
        data: { title: 'Listado de Campañas' },
      },
      {
        path: 'register',
        loadComponent: () =>
          import('./create-discounts/create-discounts.component').then((m) => m.CreateDiscountsComponent),
        data: { title: 'Nueva Campaña de Descuento' },
      },
      {
        path: 'list/edit/:id',
        loadComponent: () =>
          import('./edit-discounts/edit-discounts.component').then((m) => m.EditDiscountsComponent),
        data: { title: 'Editar Campaña de Descuento' },
      },
    ],
  },
];
