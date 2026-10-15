import { Routes } from '@angular/router';
import { FuncionarioListComponent } from './funcionario-list/funcionario-list.component';

export const routes: Routes = [
  {
    path: '',
    component: FuncionarioListComponent,
    data: { title: 'Funcionarios' },
  },
];
