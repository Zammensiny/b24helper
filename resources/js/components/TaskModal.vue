<template>
    <transition
        enter-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="isOpen" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50" @mousedown="clickOutside">
            <div ref="modalContent" class="bg-white rounded-lg shadow-lg w-full max-w-2xl mx-4 sm:mx-6 p-6 relative">
                <button @click="closeModal" class="absolute top-3 right-3 text-gray-500 hover:text-gray-800">
                    <i class="fa fa-xmark"></i>
                </button>

                <div v-if="isLoading" role="status" class="absolute -translate-x-1/2 -translate-y-1/2 top-2/4 left-1/2">
                    <!-- loader SVG -->
                </div>

                <h2 class="text-xl font-semibold mb-4">{{ task?.title }}</h2>
                <p class="text-gray-600">{{ task?.subtitle }}</p>

                <div v-if="task?.categories?.length" class="mt-4">
          <span
              v-for="category in task.categories"
              :key="category.id"
              :class="[
              'text-xs font-medium me-2 px-2.5 py-0.5 rounded-sm',
              category.color ? `bg-${category.color}-100 text-${category.color}-800 dark:bg-${category.color}-700` : 'bg-gray-100 text-gray-800 dark:bg-gray-700'
            ]"
          >
            {{ category.value }}
          </span>
                </div>

                <!-- Кнопка удаления только для админа -->
                <button
                    v-if="isAdmin && task"
                    @click="deleteTask"
                    class="mt-4 px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 flex items-center gap-2"
                >
                    <i class="fa fa-trash"></i> Удалить
                </button>

            </div>
        </div>
    </transition>
</template>

<script>
export default {
    props: {
        taskId: Number,
        isOpen: Boolean,
        isAdmin: Boolean
    },
    emits: ['close', 'deleted'],
    data() {
        return {
            task: null,
            isLoading: true,
        };
    },
    watch: {
        isOpen(newVal) {
            if (newVal && this.taskId) {
                this.fetchTask();
            }
        }
    },
    methods: {
        async fetchTask() {
            this.isLoading = true;
            this.task = null;
            try {
                const response = await fetch(`/api/tasks/${this.taskId}`);
                if (!response.ok) throw new Error('Не удалось загрузить задачу');
                this.task = await response.json();
            } catch (error) {
                console.error('Ошибка загрузки таска:', error);
            } finally {
                this.isLoading = false;
            }
        },
        closeModal() {
            this.isLoading = true;
            this.task = null;
            this.$emit('close');
        },
        clickOutside(event) {
            if (!this.$refs.modalContent || !this.$refs.modalContent.contains(event.target)) {
                this.closeModal();
            }
        },
        async deleteTask() {
            if (!confirm('Вы точно хотите удалить задачу?')) return;

            try {
                const response = await fetch(`/tasks/${this.task.id}/delete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'Ошибка удаления');
                }

                this.$emit('deleted', this.task.id);
                this.closeModal();

            } catch (err) {
                alert(err.message);
                console.error(err);
            }
        }
    }
};
</script>
