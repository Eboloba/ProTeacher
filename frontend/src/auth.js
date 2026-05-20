// frontend/src/auth.js
const TOKEN_KEY = 'auth_token';
const TOKEN_EXPIRY_KEY = 'auth_token_expiry';
const USER_KEY = 'auth_user';

export const storeAuth = (token, user, expiresIn = 86400 * 7) => {
  // 🔹 ИСПРАВЛЕНИЕ: Если токен null, не сохраняем и выходим
  if (!token) {
    console.error('Cannot store auth: token is null');
    return false;
  }

  try {
    // 🔹 БЕЗОПАСНО: Проверяем перед substring
    console.log('Storing auth:', { 
      token: token.substring(0, 10) + '...', 
      user 
    });
    
    localStorage.setItem(TOKEN_KEY, token);
    localStorage.setItem(USER_KEY, JSON.stringify(user));
    
    const expiryTime = Date.now() + (expiresIn * 1000);
    localStorage.setItem(TOKEN_EXPIRY_KEY, expiryTime.toString());
    
    return true;
  } catch (error) {
    console.error('Failed to store auth:', error);
    return false;
  }
};

export const loadToken = () => {
  try {
    const token = localStorage.getItem(TOKEN_KEY);
    const expiry = localStorage.getItem(TOKEN_EXPIRY_KEY);
    
    if (!token) return null;
    if (expiry && Date.now() >= parseInt(expiry)) {
      removeAuth();
      return null;
    }
    return token;
  } catch (error) {
    console.error('Failed to load token:', error);
    return null;
  }
};

export const loadUser = () => {
  try {
    const userStr = localStorage.getItem(USER_KEY);
    if (!userStr) return null;
    
    const user = JSON.parse(userStr);
    return {
      id: user.id,
      first_name: user.first_name || '',
      last_name: user.last_name || '',
      email: user.email || '',
      role: user.role || 'user',
      bio: user.bio || '',
      about: user.about || '',
      is_private: !!user.is_private,
      is_teacher: !!user.is_teacher,
    };
  } catch (error) {
    console.error('Failed to load user:', error);
    return null;
  }
};

export const removeAuth = () => {
  console.log('🗑️ Removing auth');
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(TOKEN_EXPIRY_KEY);
  localStorage.removeItem(USER_KEY);
};

export const isAuthenticated = () => {
  return !!loadToken();
};

export const isTeacher = () => {
  const user = loadUser();
  return user?.is_teacher === true || user?.role === 'teacher';
};

export const getCurrentUser = () => {
  return loadUser();
};