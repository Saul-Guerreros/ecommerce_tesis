import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: '',
    data: { title: 'Productos' },
    children: [
      {
        path: '',
        loadComponent: () =>
          import('./producto-list/producto-list.component').then((m) => m.ProductoListComponent),
        data: { title: 'Productos' },
      },
      {
         path: ':id/variations',
        loadComponent: () =>
      import('./product-variations/product-variations.component').then((m) => m.ProductVariationsComponent),
      data: { title: 'Especificaciones y Variaciones' },
      },
    ],
  },
];