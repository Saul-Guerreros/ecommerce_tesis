import { Routes } from '@angular/router';
import { ClienteListComponent } from './cliente-list/cliente-list.component';

export const routes: Routes = [
  {
    path: '',
    component: ClienteListComponent,
    data: { title: 'Clientes' },
  },
];