/**
 * Refleja el objeto "user" que devuelve la API en /auth/login, /auth/login_ecommerce
 * y /auth/me (ver AuthController::respondWithToken / AuthController::me en el backend).
 */
export interface User {
  full_name: string;
  email: string;
  rol: string;
  permisos: string[];
}
