import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { TiposClienteRoutingModule } from './tipos-cliente-routing.module';
import { TiposClienteComponent } from './tipos-cliente.component';
import { ListTipoClienteComponent } from './list-tipo-cliente/list-tipo-cliente.component';
import { FormTipoClienteComponent } from './form-tipo-cliente/form-tipo-cliente.component';
import { DeleteTipoClienteComponent } from './delete-tipo-cliente/delete-tipo-cliente.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule, TiposClienteRoutingModule, TiposClienteComponent,
    ListTipoClienteComponent, FormTipoClienteComponent, DeleteTipoClienteComponent,
  ],
})
export class TiposClienteModule {}