import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { CuentasPorPagarComponent } from './cuentas-por-pagar.component';
import { ListCuentasPorPagarComponent } from './list-cuentas-por-pagar/list-cuentas-por-pagar.component';

const routes: Routes = [
  {
    path: '',
    component: CuentasPorPagarComponent,
    children: [
      { path: '', component: ListCuentasPorPagarComponent },  // <-- debe ser este, no DetalleCuotasPagarComponent
    ],
  },
];

@NgModule({ imports: [RouterModule.forChild(routes)], exports: [RouterModule] })
export class CuentasPorPagarRoutingModule {}