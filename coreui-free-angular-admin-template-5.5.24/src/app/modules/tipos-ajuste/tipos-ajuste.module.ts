import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { TiposAjusteRoutingModule } from './tipos-ajuste-routing.module';
import { TiposAjusteComponent } from './tipos-ajuste.component';
import { ListTipoAjusteComponent } from './list-tipo-ajuste/list-tipo-ajuste.component';
import { FormTipoAjusteComponent } from './form-tipo-ajuste/form-tipo-ajuste.component';
import { DeleteTipoAjusteComponent } from './delete-tipo-ajuste/delete-tipo-ajuste.component';

@NgModule({
  declarations: [],
  imports: [CommonModule, TiposAjusteRoutingModule, TiposAjusteComponent,
    ListTipoAjusteComponent, FormTipoAjusteComponent, DeleteTipoAjusteComponent],
})
export class TiposAjusteModule {}