import { createApp } from 'vue'
import TaskSearch from './components/TaskSearch.vue'
import TaskList from './components/TaskList.vue'
import '@fortawesome/fontawesome-free/css/all.min.css';

const app = createApp({
    data() {
        return {
            tasks: [],
        }
    },
    methods: {
        updateTasks(tasks) {
            this.tasks = tasks;
        }
    },
    template: `
        <div class="container mx-auto">
        <task-search @update-tasks="updateTasks" />
        <task-list :tasks="tasks" />
        </div>
    `
});

app.component('task-search', TaskSearch)
app.component('task-list', TaskList)

app.mount('#app')
