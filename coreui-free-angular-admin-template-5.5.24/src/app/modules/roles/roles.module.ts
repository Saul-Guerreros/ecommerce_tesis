import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RolesRoutingModule } from './roles-routing.module';
import { RolesComponent } from './roles.component';
import { ListRolComponent } from './list-rol/list-rol.component';
import { FormRolComponent } from './form-rol/form-rol.component';
import { DeleteRolComponent } from './delete-rol/delete-rol.component';

@NgModule({
  declarations: [],
  imports: [CommonModule, RolesRoutingModule, RolesComponent, ListRolComponent, FormRolComponent, DeleteRolComponent],
})
export class RolesModule {}