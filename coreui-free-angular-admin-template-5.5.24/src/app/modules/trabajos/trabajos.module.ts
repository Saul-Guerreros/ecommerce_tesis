import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { TrabajosRoutingModule } from './trabajos-routing.module';
import { TrabajosComponent } from './trabajos.component';
import { ListTrabajoComponent } from './list-trabajo/list-trabajo.component';
import { FormTrabajoComponent } from './form-trabajo/form-trabajo.component';
import { DeleteTrabajoComponent } from './delete-trabajo/delete-trabajo.component';

@NgModule({
  declarations: [],
  imports: [CommonModule, TrabajosRoutingModule, TrabajosComponent, ListTrabajoComponent, FormTrabajoComponent, DeleteTrabajoComponent],
})
export class TrabajosModule {}
