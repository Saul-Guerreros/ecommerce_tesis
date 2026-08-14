import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { BrandsRoutingModule } from './brands-routing.module';
import { BrandsComponent } from './brands.component';
import { ListBrandComponent } from './list-brand/list-brand.component';
import { CreateBrandComponent } from './create-brand/create-brand.component';
import { EditBrandComponent } from './edit-brand/edit-brand.component';
import { DeleteBrandComponent } from './delete-brand/delete-brand.component';


@NgModule({
  declarations: [],
  imports: [
    CommonModule,
    BrandsRoutingModule,
    BrandsComponent,
    ListBrandComponent,
    CreateBrandComponent,
    EditBrandComponent,
    DeleteBrandComponent,
  ]
})
export class BrandsModule { }
