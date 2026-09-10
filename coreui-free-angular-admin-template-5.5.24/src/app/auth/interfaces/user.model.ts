/**
 * Refleja el objeto "user" que devuelve la API en /auth/login,
 * /auth/login_ecommerce y /auth/me.
>>>>>>> origin/saul
 */
export interface User {
  full_name: string;
  email: string;
  rol: string;
  permisos: string[];
}
