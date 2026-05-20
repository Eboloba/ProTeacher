import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import CatalogView from '../views/CatalogView.vue'
import CourseView from '../views/CourseView.vue'
import LearningView from '../views/LearningView.vue'
import ProfileView from '../views/ProfileView.vue'
import ProfileEditView from '../views/ProfileEditView.vue'
import TeachingView from '../views/TeachingView.vue'
import CourseEditView from '../views/CourseEditView.vue'
import MyLearningView from '../views/MyLearningView.vue'
import AdminView from '../views/AdminView.vue'
import NotificationsView from '../views/NotificationsView.vue'

const routes = [
    {
        path: '/',
        name: 'Home',
        component: HomeView
    },
    {
        path: '/catalog',
        name: 'Catalog',
        component: CatalogView
    },
    {
        path: '/course/:id',
        name: 'Course',
        component: CourseView
    },
    {
        path: '/learning/:courseId',
        name: 'Learning',
        component: LearningView
    },
    {
        path: '/profile',
        name: 'Profile',
        component: ProfileView
    },
    {
        path: '/profile/edit',
        name: 'ProfileEdit',
        component: ProfileEditView
    },
    {
        path: '/teaching',
        name: 'Teaching',
        component: TeachingView,
        meta: { requiresAuth: true, teacher: true }
    },
    {
        path: '/teaching/course/:id/edit',
        name: 'CourseEdit',
        component: CourseEditView,
        meta: { requiresAuth: true, teacher: true }
    },
    {
        path: '/my-learning',
        name: 'MyLearning',
        component: MyLearningView,
        meta: { requiresAuth: true }
    },
    { 
        path: '/notifications', 
        name: 'Notifications', 
        component: NotificationsView,
        meta: { requiresAuth: true } 
    },
    {
        path: '/admin',
        name: 'Admin',
        component: AdminView,
        meta: { requiresAuth: true }
    }
]

export const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior() {
        return { top: 0 }
    }
})

export default router