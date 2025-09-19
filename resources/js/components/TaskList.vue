<template>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 px-4 sm:px-6">
        <div
            v-for="task in tasks"
            :key="task.id"
            class="bg-white p-4 border border-gray-400 rounded-lg shadow-md cursor-pointer flex flex-col justify-between"
            @click="openTaskModal(task.id)"
        >
            <div>
                <h3 class="text-xl font-semibold">{{ task.title }}</h3>
                <p class="text-gray-600 mb-2">{{ task.subtitle }}</p>

                <!-- Категории -->
                <div class="mb-3">
                    <span
                        v-for="category in task.categories"
                        :key="category.id"
                        class="text-xs font-medium me-2 px-2.5 py-0.5 rounded-sm"
                        :class="{
                            'bg-red-100 text-red-800 dark:bg-red-700': category.color === 'red',
                            'bg-blue-100 text-blue-800 dark:bg-blue-700': category.color === 'blue',
                            'bg-green-100 text-green-800 dark:bg-green-700': category.color === 'green',
                            'bg-gray-100 text-gray-800 dark:bg-gray-700': category.color === 'gray',
                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-700': category.color === 'yellow',
                            'bg-indigo-100 text-indigo-800 dark:bg-indigo-700': category.color === 'indigo',
                            'bg-purple-100 text-purple-800 dark:bg-purple-700': category.color === 'purple',
                            'bg-pink-100 text-pink-800 dark:bg-pink-700': category.color === 'pink',
                        }"
                    >
                        {{ category.value }}
                    </span>
                </div>
            </div>

            <!-- Дата создания -->
            <div class="flex items-center justify-start text-xs text-gray-500 mt-3">
                {{ formatDate(task.created_at) }}
            </div>
        </div>
    </div>

    <TaskModal
        :taskId="selectedTaskId"
        :isOpen="isModalOpen"
        :is-admin="isAdmin"
        @close="closeTaskModal"
        @deleted="handleDeletedTask"
    />
</template>

<script>
import TaskModal from './TaskModal.vue';

export default {
    components: { TaskModal },
    props: ['tasks', 'isAdmin'],
    emits: ['update-tasks'],
    data() {
        return {
            isModalOpen: false,
            selectedTaskId: null
        };
    },
    methods: {
        openTaskModal(taskId) {
            this.selectedTaskId = taskId;
            this.isModalOpen = true;
        },
        closeTaskModal() {
            this.isModalOpen = false;
            this.selectedTaskId = null;
        },
        handleDeletedTask(taskId) {
            this.$emit('update-tasks', this.tasks.filter(task => task.id !== taskId));
        },
        formatDate(dateString) {
            if (!dateString) return "";
            const date = new Date(dateString);
            const day = String(date.getDate()).padStart(2, "0");
            const month = String(date.getMonth() + 1).padStart(2, "0");
            const year = date.getFullYear();
            return `${day}.${month}.${year}`;
        }
    }
};
</script>
