import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AjustesInventarioRoutingModule } from './ajustes-inventario-routing.module';
import { AjustesInventarioComponent } from './ajustes-inventario.component';
import { ListAjusteComponent } from './list-ajuste/list-ajuste.component';
import { FormAjusteComponent } from './form-ajuste/form-ajuste.component';



@NgModule({
  declarations: [],
  imports: [
    CommonModule, AjustesInventarioRoutingModule, AjustesInventarioComponent,
    ListAjusteComponent, FormAjusteComponent,
  ],
})
export class AjustesInventarioModule {}