import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PreciosRoutingModule } from './precios-routing.module';
import { PreciosComponent } from './precios.component';
import { ListPrecioComponent } from './list-precio/list-precio.component';
import { FormPrecioComponent } from './form-precio/form-precio.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule, PreciosRoutingModule, PreciosComponent,
    ListPrecioComponent, FormPrecioComponent,
  ],
})
export class PreciosModule {}