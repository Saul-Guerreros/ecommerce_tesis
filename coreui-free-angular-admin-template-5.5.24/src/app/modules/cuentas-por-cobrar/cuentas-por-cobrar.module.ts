import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CuentasPorCobrarRoutingModule } from './cuentas-por-cobrar-routing.module';
import { CuentasPorCobrarComponent } from './cuentas-por-cobrar.component';
import { ListCuentasPorCobrarComponent } from './list-cuentas-por-cobrar/list-cuentas-por-cobrar.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule,
    CuentasPorCobrarRoutingModule,
    CuentasPorCobrarComponent,
    ListCuentasPorCobrarComponent,
  ],
})
export class CuentasPorCobrarModule {}