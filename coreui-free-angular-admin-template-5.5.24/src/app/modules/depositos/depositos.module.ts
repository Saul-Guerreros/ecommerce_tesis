import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { DepositosRoutingModule } from './depositos-routing.module';
import { DepositosComponent } from './depositos.component';
import { ListDepositoComponent } from './list-deposito/list-deposito.component';
import { FormDepositoComponent } from './form-deposito/form-deposito.component';
import { DeleteDepositoComponent } from './delete-deposito/delete-deposito.component';

@NgModule({
  declarations: [],
  imports: [
    CommonModule,
    DepositosRoutingModule,
    DepositosComponent,
    ListDepositoComponent,
    FormDepositoComponent,
    DeleteDepositoComponent,
  ],
})
export class DepositosModule {}