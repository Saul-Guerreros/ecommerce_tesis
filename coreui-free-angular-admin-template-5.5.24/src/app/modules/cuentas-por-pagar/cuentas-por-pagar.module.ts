import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CuentasPorPagarRoutingModule } from './cuentas-por-pagar-routing.module';
import { CuentasPorPagarComponent } from './cuentas-por-pagar.component';
import { ListCuentasPorPagarComponent } from './list-cuentas-por-pagar/list-cuentas-por-pagar.component';

@NgModule({
  declarations: [],
  imports: [CommonModule, CuentasPorPagarRoutingModule, CuentasPorPagarComponent, ListCuentasPorPagarComponent],
})
export class CuentasPorPagarModule {}

