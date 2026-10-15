import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { TiposDocumentoRoutingModule } from './tipos-documento-routing.module';
import { TiposDocumentoComponent } from './tipos-documento.component';
import { ListTipoDocumentoComponent } from './list-tipo-documento/list-tipo-documento.component';
import { FormTipoDocumentoComponent } from './form-tipo-documento/form-tipo-documento.component';
import { DeleteTipoDocumentoComponent } from './delete-tipo-documento/delete-tipo-documento.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule, TiposDocumentoRoutingModule, TiposDocumentoComponent,
    ListTipoDocumentoComponent, FormTipoDocumentoComponent, DeleteTipoDocumentoComponent,
  ],
})
export class TiposDocumentoModule {}
