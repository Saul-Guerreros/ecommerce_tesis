// unidades-medida.module.ts
import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { UnidadesMedidaRoutingModule } from './unidades-medida-routing.module';
import { UnidadesMedidaComponent } from './unidades-medida.component';
import { ListUnidadMedidaComponent } from './list-unidad-medida/list-unidad-medida.component';
import { FormUnidadMedidaComponent } from './form-unidad-medida/form-unidad-medida.component';
import { DeleteUnidadMedidaComponent } from './delete-unidad-medida/delete-unidad-medida.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule, UnidadesMedidaRoutingModule, UnidadesMedidaComponent,
    ListUnidadMedidaComponent, FormUnidadMedidaComponent, DeleteUnidadMedidaComponent,
  ],
})
export class UnidadesMedidaModule {}