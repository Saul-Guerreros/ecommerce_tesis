import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: '',
    data: { title: 'Publicación Web' },
    children: [
      {
        path: '',
        loadComponent: () =>
          import('./publicacion-list/publicacion-list.component').then((m) => m.PublicacionListComponent),
        data: { title: 'Publicación Web' },
      },
      {
        // Editar/crear la publicación de un producto puntual.
        // URL resultante: /publicaciones/:id (antes era /productos/:id/publicacion).
        path: ':id',
        loadComponent: () =>
          import('./publicacion-web/publicacion-web.component').then((m) => m.PublicacionWebComponent),
        data: { title: 'Publicación Web' },
      },
    ],
  },
];
