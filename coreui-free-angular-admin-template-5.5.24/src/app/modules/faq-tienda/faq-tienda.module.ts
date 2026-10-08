import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FaqTiendaRoutingModule } from './faq-tienda-routing.module';
import { FaqTiendaComponent } from './faq-tienda.component';
import { ListFaqTiendaComponent } from './list-faq-tienda/list-faq-tienda.component';
import { FormFaqTiendaComponent } from './form-faq-tienda/form-faq-tienda.component';
import { DeleteFaqTiendaComponent } from './delete-faq-tienda/delete-faq-tienda.component';

@NgModule({
  declarations: [],
  imports: [CommonModule, FaqTiendaRoutingModule, FaqTiendaComponent,
    ListFaqTiendaComponent, FormFaqTiendaComponent, DeleteFaqTiendaComponent],
})
export class FaqTiendaModule {}