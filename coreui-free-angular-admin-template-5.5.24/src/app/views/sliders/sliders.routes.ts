import { Routes } from '@angular/router';

export const SLIDERS_ROUTES: Routes = [
  {
    path: '',
    children: [
      { path: '', redirectTo: 'list', pathMatch: 'full' },
      {
        path: 'list',
        loadComponent: () =>
          import('./list-slider/list-slider.component').then((m) => m.ListSliderComponent),
        data: { title: 'Sliders' },
      },
      {
        path: 'crear',
        loadComponent: () =>
          import('./create-slider/create-slider.component').then((m) => m.CreateSliderComponent),
        data: { title: 'Nuevo Slider' },
      },
      {
        path: 'editar/:id',
        loadComponent: () =>
          import('./edit-slider/edit-slider.component').then((m) => m.EditSliderComponent),
        data: { title: 'Editar Slider' },
      },
    ],
  },
];
