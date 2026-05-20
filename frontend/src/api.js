// frontend/src/api.js
import { loadToken, removeAuth } from './auth';

const API_URL = "http://localhost:8085/api";

async function request(path, options = {}) {
  const headers = { "Content-Type": "application/json", ...(options.headers || {}) };
  const token = loadToken();
  if (token) headers.Authorization = `Bearer ${token}`;

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 10000);

  let response;
  try {
    response = await fetch(`${API_URL}${path}`, {
      ...options,
      headers,
      signal: controller.signal,
    });
    clearTimeout(timeout);
  } catch (err) {
    if (err.name === "AbortError") throw new Error("Request timeout");
    throw new Error(`Backend недоступен: ${API_URL}`);
  }

  let rawText = '';
  try {
    rawText = await response.clone().text();
  } catch {
    rawText = '';
  }

  let data;
  try {
    data = rawText ? JSON.parse(rawText) : {};
  } catch (parseError) {
    console.error('JSON parse error:', parseError);
    console.error('Raw response:', rawText.substring(0, 500));

    // Если не JSON и не 204 — это ошибка
    if (response.status !== 204) {
      throw new Error(`Invalid API response: ${response.status} ${rawText.substring(0, 200)}`);
    }
    data = {};
  }

  if (response.status === 401) {
    if (path.includes('/auth/')) {
      throw new Error(data.error || "Unauthorized");
    }
    removeAuth();
    window.dispatchEvent(new Event("unauthorized"));
    throw new Error("Session expired");
  }

  if (!response.ok) {
    throw new Error(data.error || data.message || `API error: ${response.status}`);
  }

  return data;
}

export const api = {
  register(payload) {
    return request("/auth/register", {
      method: "POST",
      body: JSON.stringify(payload)
    });
  },

  login(payload) {
    return request("/auth/login", {
      method: "POST",
      body: JSON.stringify(payload)
    });
  },

  getNotifications() {
    return request('/notifications');
  },

  markNotificationAsRead(id) {
    return request(`/notifications/${id}/read`, { method: 'POST' });
  },

  markAllNotificationsAsRead() {
    return request('/notifications/read-all', { method: 'POST' });
  },



  updateProfile(payload) {
    return request("/profile", {
      method: "PUT",
      body: JSON.stringify(payload)
    });
  },

  me() {
    return request("/auth/me");
  },

  getCourses() {
    return request("/courses");
  },

  getCourse(id) {
    return request(`/courses/${id}`);
  },

  getUserEnrollments() {
    return request('/user/enrollments');
  },

  updateCourse(courseId, payload) {
    return request(`/courses/${courseId}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  adminUpdateCourse(courseId, payload) {
    return request(`/admin/courses/${courseId}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  checkWishlistStatus(courseId) {
    return request(`/courses/${courseId}/wishlist`);
  },

  toggleWishlist(courseId) {
    return request(`/courses/${courseId}/wishlist`, { method: 'POST' });
  },

  getUserWishlist() {
    return request('/user/wishlist');
  },

  getQuizQuestions(lessonId) {
    return request(`/lessons/${lessonId}/quiz`);
  },

  createQuizQuestion(lessonId, payload) {
    return request(`/lessons/${lessonId}/quiz`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  updateQuizQuestion(questionId, payload) {
    return request(`/quiz/${questionId}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  deleteQuizQuestion(questionId) {
    return request(`/quiz/${questionId}`, {
      method: 'DELETE',
    });
  },

  getUserCertificates() {
    return request('/user/certificates');
  },
  issueCertificate(courseId, courseTitle) {
    return request('/certificates/issue', {
      method: 'POST',
      body: JSON.stringify({ course_id: courseId, course_title: courseTitle }),
    });
  },

  deleteOwnCourse(courseId) {
    return request(`/courses/${courseId}`, { method: 'DELETE' });
  },

  createUser(payload) {
    return request('/admin/users', { method: 'POST', body: JSON.stringify(payload) });
  },
  updateUser(id, payload) {
    return request(`/admin/users/${id}`, { method: 'PUT', body: JSON.stringify(payload) });
  },
  deleteUser(id) {
    return request(`/admin/users/${id}`, { method: 'DELETE' });
  },
  getAdminCourses() {
    return request('/admin/courses');
  },
  deleteCourse(id) {
    return request(`/admin/courses/${id}`, { method: 'DELETE' });
  },
  getPendingCourses() {
    return request('/admin/courses/pending');
  },
  updateCourseStatus(courseId, status, options = {}) {
    const body = {
      status,
      send_notification: options.send_notification ?? true,
      course_title: options.course_title,
      teacher_name: options.teacher_name
    }

    return request(`/admin/courses/${courseId}/status`, {
      method: 'PUT',
      body: JSON.stringify(body)
    })
  },

  // Модули
  createModule(courseId, payload) {
    return request(`/courses/${courseId}/modules`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  updateModule(moduleId, payload) {
    return request(`/modules/${moduleId}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  deleteModule(moduleId) {
    return request(`/modules/${moduleId}`, {
      method: 'DELETE',
    });
  },

  // Уроки
  createLesson(moduleId, payload) {
    return request(`/modules/${moduleId}/lessons`, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  },

  updateLesson(lessonId, payload) {
    return request(`/lessons/${lessonId}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  },

  deleteLesson(lessonId) {
    return request(`/lessons/${lessonId}`, {
      method: 'DELETE',
    });
  },

  getCourseStructure(courseId) {
    return request(`/courses/${courseId}/structure`);
  },

  getLesson(lessonId) {
    return request(`/lessons/${lessonId}`);
  },

  completeLesson(lessonId, answers = null) {
    return request(`/lessons/${lessonId}/complete`, {
      method: "POST",
      body: JSON.stringify({ answers }),
    });
  },

  createCourse(payload) {
    return request("/courses", {
      method: "POST",
      body: JSON.stringify(payload)
    });
  },

  enroll(courseId) {
    return request(`/courses/${courseId}/enroll`, { method: "POST" });
  },

  getTeacherCourses() {
    return request("/teacher/courses");
  },

  getAdminUsers() {
    return request("/admin/users");
  },

  logout() {
    removeAuth();
  },
};