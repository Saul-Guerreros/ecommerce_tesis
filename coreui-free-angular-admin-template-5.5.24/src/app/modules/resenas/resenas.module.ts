import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ResenasRoutingModule } from './resenas-routing.module';
import { ResenasComponent } from './resenas.component';
import { ListResenaComponent } from './list-resena/list-resena.component';
import { FormResenaComponent } from './form-resena/form-resena.component';
import { OcultarResenaComponent } from './ocultar-resena/ocultar-resena.component';
import { DeleteResenaComponent } from './delete-resena/delete-resena.component';

@NgModule({
  declarations: [],
  imports: [CommonModule, ResenasRoutingModule, ResenasComponent,
    ListResenaComponent, FormResenaComponent, OcultarResenaComponent, DeleteResenaComponent],
})
export class ResenasModule {}