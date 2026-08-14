import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { BrandsComponent } from './brands.component';
import { ListBrandComponent } from './list-brand/list-brand.component';
import { CreateBrandComponent } from './create-brand/create-brand.component';
import { EditBrandComponent } from './edit-brand/edit-brand.component';

const routes: Routes = [
  {
    path: '',
    component: BrandsComponent,
    children: [
      { path: '', component: ListBrandComponent },
      { path: 'new', component: CreateBrandComponent },
      { path: 'editar/:id', component: EditBrandComponent },
    ],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class BrandsRoutingModule {}