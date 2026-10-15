import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CargosRoutingModule } from './cargos-routing.module';
import { CargosComponent } from './cargos.component';
import { ListCargoComponent } from './list-cargo/list-cargo.component';
import { FormCargoComponent } from './form-cargo/form-cargo.component';
import { DeleteCargoComponent } from './delete-cargo/delete-cargo.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule, CargosRoutingModule, CargosComponent,
    ListCargoComponent, FormCargoComponent, DeleteCargoComponent,
  ],
})
export class CargosModule {}