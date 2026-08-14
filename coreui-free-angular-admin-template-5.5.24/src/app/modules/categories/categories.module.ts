import { NgModule } from '@angular/core';

import { CategoriesRoutingModule } from './categories-routing.module';
import { CategoriesComponent } from './categories.component';
import { ListCategorieComponent } from './list-categorie/list-categorie.component';
import { CreateCategorieComponent } from './create-categorie/create-categorie.component';
import { EditCategorieComponent } from './edit-categorie/edit-categorie.component';
import { DeleteCategorieComponent } from './delete-categorie/delete-categorie.component';


@NgModule({
  declarations: [],
  imports: [
    CategoriesRoutingModule,
    CategoriesComponent,
    ListCategorieComponent,
    CreateCategorieComponent,
    EditCategorieComponent,
    DeleteCategorieComponent,
    
  ]
})
export class CategoriesModule { }
