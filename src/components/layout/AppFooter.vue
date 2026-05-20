<template>
  <footer class="footer">
    <div class="footer__container">
      <div class="footer__grid">
        <div class="footer__section">
          <h3 class="footer__title">TeacherPro</h3>
          <p class="footer__text">
            Платформа онлайн-образования для изучения программирования и IT-технологий.
          </p>
        </div>
        
        <div class="footer__section">
          <h4 class="footer__subtitle">Навигация</h4>
          <ul class="footer__links">
            <li><a href="/">Главная</a></li>
            <li><a href="/catalog">Каталог курсов</a></li>
            <li><a href="/about">О нас</a></li>
            <li><a href="/contacts">Контакты</a></li>
          </ul>
        </div>
        
        <div class="footer__section">
          <h4 class="footer__subtitle">Помощь</h4>
          <ul class="footer__links">
            <li><a href="/faq">FAQ</a></li>
            <li><a href="/support">Поддержка</a></li>
            <li><a href="/terms">Условия использования</a></li>
            <li><a href="/privacy">Политика конфиденциальности</a></li>
          </ul>
        </div>
        
        <div class="footer__section">
          <h4 class="footer__subtitle">Контакты</h4>
          <p class="footer__text">Email: support@teacherpro.ru</p>
          <p class="footer__text">Тел: +7 (999) 123-45-67</p>
        </div>
      </div>
      
      <div class="footer__bottom">
        <p>&copy; {{ new Date().getFullYear() }} TeacherPro. Все права защищены.</p>
      </div>
    </div>
  </footer>
</template>

<style scoped>
.footer {
  background: #000000;
  color: #ffffff;
  padding: 60px 0 30px;
  margin-top: 80px;
}

.footer__container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 24px;
}

.footer__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 40px;
  margin-bottom: 40px;
}

.footer__title {
  font-size: 24px;
  font-weight: 800;
  margin: 0 0 16px;
  color: #ffffff;
}

.footer__subtitle {
  font-size: 16px;
  font-weight: 700;
  margin: 0 0 16px;
  color: #ffffff;
}

.footer__text {
  font-size: 14px;
  color: #a0aec0;
  line-height: 1.6;
  margin: 0 0 12px;
}

.footer__links {
  list-style: none;
  padding: 0;
  margin: 0;
}

.footer__links li {
  margin-bottom: 8px;
}

.footer__links a {
  color: #a0aec0;
  text-decoration: none;
  font-size: 14px;
  transition: color 0.2s;
}

.footer__links a:hover {
  color: #ffffff;
}

.footer__bottom {
  padding-top: 30px;
  border-top: 1px solid #2d2d2d;
  text-align: center;
}

.footer__bottom p {
  font-size: 14px;
  color: #718096;
  margin: 0;
}
</style>