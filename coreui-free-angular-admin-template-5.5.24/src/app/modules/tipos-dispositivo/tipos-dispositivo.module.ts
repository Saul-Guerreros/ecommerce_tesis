import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { TiposDispositivoRoutingModule } from './tipos-dispositivo-routing.module';
import { TiposDispositivoComponent } from './tipos-dispositivo.component';
import { ListTipoDispositivoComponent } from './list-tipo-dispositivo/list-tipo-dispositivo.component';
import { FormTipoDispositivoComponent } from './form-tipo-dispositivo/form-tipo-dispositivo.component';
import { DeleteTipoDispositivoComponent } from './delete-tipo-dispositivo/delete-tipo-dispositivo.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule, TiposDispositivoRoutingModule, TiposDispositivoComponent,
    ListTipoDispositivoComponent, FormTipoDispositivoComponent, DeleteTipoDispositivoComponent,
  ],
})
export class TiposDispositivoModule {}
