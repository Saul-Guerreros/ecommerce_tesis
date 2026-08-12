import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';

const routes: Routes = [
  //  {
  //   path: '',
  //   component: CategoriesComponent,
  //   children:[
  //     {
  //       path:'register',
  //       component: CreateCategorieComponent
  //     },
  //     {
  //       path:'list/edit/:id',
  //       component: EditCategorieComponent
  //     },
  //     {
  //       path:'list',
  //       component: ListCategorieComponent
  //     }
  //   ]
  // }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class CategoriesRoutingModule { }
