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
        async fetchTasks(query = '') {
            let url = '/api/tasks';
            if (query) {
                url += `?query=${query}`;
            }
            const response = await fetch(url);
            const tasks = await response.json();

            console.log(tasks)


            this.tasks = tasks;
        },
        updateTasks(tasks) {
            this.tasks = tasks;
        }
    },
    mounted() {
        this.fetchTasks();
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
