import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FallasRoutingModule } from './fallas-routing.module';
import { FallasComponent } from './fallas.component';
import { ListFallaComponent } from './list-falla/list-falla.component';
import { FormFallaComponent } from './form-falla/form-falla.component';
import { DeleteFallaComponent } from './delete-falla/delete-falla.component';

@NgModule({
  declarations: [],
  imports: [CommonModule, FallasRoutingModule, FallasComponent, ListFallaComponent, FormFallaComponent, DeleteFallaComponent],
})
export class FallasModule {}
