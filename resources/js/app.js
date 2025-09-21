import { createApp } from 'vue'
import TaskSearch from './components/TaskSearch.vue'
import TaskList from './components/TaskList.vue'
import '@fortawesome/fontawesome-free/css/all.min.css';
import hljs from "highlight.js/lib/core";
import javascript from "highlight.js/lib/languages/javascript";
import php from "highlight.js/lib/languages/php";
import "highlight.js/styles/github.css";

window.hljs = hljs;

hljs.registerLanguage("javascript", javascript);
hljs.registerLanguage("php", php);

const app = createApp({
    data() {
        return {
            tasks: [],
            isAdmin: window.Laravel.isAdmin,
            isAuthenticated: window.Laravel.isAuthenticated,
            showHidden: false, // флаг скрытых задач
        }
    },
    methods: {
        async fetchTasks(query = '') {
            const params = new URLSearchParams();
            if (query) params.append('query', query);

            if (this.showHidden) params.append('secret', window.Laravel.taskSecret);

            const url = `/api/tasks?${params.toString()}`;
            const response = await fetch(url);
            this.tasks = await response.json();
        },
        updateTasks(tasks) {
            this.tasks = tasks;
        },
        unlockHidden() {
            this.showHidden = true;
            this.fetchTasks(); // перезапрос с секретом
        },
        openCreateModal() {
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
            @unlock-hidden="unlockHidden"
        />
        <task-list
            ref="taskList"
            :tasks="tasks"
            :is-admin="isAdmin"
            :show-hidden="showHidden"
        @update-tasks="updateTasks"
        />
        </div>
    `
});

app.component('task-search', TaskSearch)
app.component('task-list', TaskList)

app.mount('#app')
