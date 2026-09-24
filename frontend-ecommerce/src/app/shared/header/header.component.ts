import { Component, afterNextRender, afterRender } from '@angular/core';
import { HomeService } from '../../pages/home/service/home.service';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { CookieService } from 'ngx-cookie-service';
import { CartService } from '../../pages/home/service/cart.service';
import { ToastrService } from 'ngx-toastr';

@Component({
  selector: 'app-header',
  standalone: true,
  imports: [FormsModule,RouterModule,CommonModule],
  templateUrl: './header.component.html',
  styleUrl: './header.component.css'
})
export class HeaderComponent {

  categories_menus:any = [];
  currency:string = 'PEN';

  // TODO: reemplazar por datos reales desde un servicio (ej. homeService.brands())
  brands:any = [
    { id: 1, name: 'Apple', logo: 'assets/images/brands/apple.svg' },
    { id: 2, name: 'Samsung', logo: 'assets/images/brands/samsung.svg' },
    { id: 3, name: 'Sony', logo: 'assets/images/brands/sony.svg' },
    { id: 4, name: 'Microsoft', logo: 'assets/images/brands/microsoft.svg' },
    { id: 5, name: 'Intel', logo: 'assets/images/brands/intel.svg' },
    { id: 6, name: 'HP', logo: 'assets/images/brands/hp.svg' },
    { id: 7, name: 'LG', logo: 'assets/images/brands/lg.svg' },
    { id: 8, name: 'Lenovo', logo: 'assets/images/brands/lenovo.svg' },
    { id: 9, name: 'ASUS', logo: 'assets/images/brands/asus.svg' },
    { id: 10, name: 'Acer', logo: 'assets/images/brands/acer.svg' },
    { id: 11, name: 'Dell', logo: 'assets/images/brands/dell.svg' },
    { id: 12, name: 'Canon', logo: 'assets/images/brands/canon.svg' },
  ];

  user:any;
  listCarts:any = [];
  totalCarts:number = 0;
  isLoading:boolean = false;
  searchT:string = '';
  constructor(
    public homeService: HomeService,
    public cookieService: CookieService,
    public cartService: CartService,
    private toastr: ToastrService,
  ) {
    afterNextRender(() => {
      this.homeService.menus().subscribe({
        next: (resp:any) => {
          //console.log(resp);
          this.categories_menus = resp.categories_menus;
        },
        error: (err) => {
          console.error('No se pudieron cargar las categorías del menú:', err.message);
          this.categories_menus = [];
        }
      })
      this.currency = this.cookieService.get("currency") ? this.cookieService.get("currency") : 'PEN';
      this.user = this.cartService.authService.user;
      
      if(this.user){
        this.cartService.listCart().subscribe((resp:any) => {
          //console.log(resp);
          resp.carts.data.forEach((cart:any) => {
            if(cart.currency != this.currency){
              this.cookieService.set("currency",cart.currency);
              setTimeout(() => {
                window.location.reload();
              }, 25);
            }
            this.cartService.changeCart(cart)
          });
        })
      }
    })
    afterRender(() => {
      setTimeout(() => {
        this.isLoading = true;
      }, 50);
    })
  }

  ngOnInit(): void {
    //Called after the constructor, initializing input properties, and the first call to ngOnChanges.
    //Add 'implements OnInit' to the class.
    this.cartService.currentDataCart$.subscribe((resp:any) => {
      // console.log(resp);
      this.listCarts = resp;
      this.totalCarts = this.listCarts.reduce((sum:number, item:any) => sum + item.total, 0);
    })
  }
  logout(){
    this.cartService.authService.logout();
    setTimeout(() => {
      window.location.reload()
    }, 50);
  }
  deleteCart(CART:any) {
    this.cartService.deleteCart(CART.id).subscribe((resp:any) => {
      this.toastr.info("Eliminación","Se elimino el producto "+CART.product.title + " del carrito de compra");
      this.cartService.removeCart(CART);
    })
  }

  getIconMenu(menu:any){
    var miDiv:any = document.getElementById('icon-'+menu.id);
    miDiv.innerHTML = menu.icon; 
    return '';
  }

  changeCurrency(val:string){
    if(this.user){
      this.cartService.deleteCartsAll().subscribe((resp:any) => {
        this.cookieService.set("currency",val);
        window.location.reload();
        //console.log(resp);
      })
    }else{
      this.cookieService.set("currency",val);
      setTimeout(() => {
        window.location.reload();
      }, 25);
    }
  }

  searchProduct(){
    window.location.href = "/productos-busqueda?search="+this.searchT;
  }
}