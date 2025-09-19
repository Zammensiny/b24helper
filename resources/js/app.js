import { createApp } from 'vue'
import TaskSearch from './components/TaskSearch.vue'
import TaskList from './components/TaskList.vue'
import '@fortawesome/fontawesome-free/css/all.min.css';

const app = createApp({
    data() {
        return {
            tasks: [],
            isAdmin: window.Laravel.isAdmin,
            isAuthenticated: window.Laravel.isAuthenticated,
        }
    },
    methods: {
        async fetchTasks(query = '') {
            let url = '/api/tasks';
            if (query) {
                url += `?query=${query}`;
            }
            const response = await fetch(url);
            const tasks = await response.json();
            this.tasks = tasks;
        },
        updateTasks(tasks) {
            this.tasks = tasks;
        },

        openCreateModal() {
            // через ref обращаемся к TaskList и вызываем метод
            this.$refs.taskList.openTaskModal(null);
        }
    },
    mounted() {
        this.fetchTasks();
    },
    template: `
        <div class="container mx-auto">
        <task-search
            :is-authenticated="isAuthenticated"
            :is-admin="isAdmin"
            @update-tasks="updateTasks"
            @open-create-modal="openCreateModal"
        />
        <task-list
            ref="taskList"
            :tasks="tasks"
            :is-admin="isAdmin"
            @update-tasks="updateTasks"
        />
        </div>
    `
});

app.component('task-search', TaskSearch)
app.component('task-list', TaskList)

app.mount('#app')
