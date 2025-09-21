<template>
    <transition
        enter-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="isOpen" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50"
             @mousedown="clickOutside">
            <div ref="modalContent"
                 class="bg-white rounded-lg shadow-lg w-full max-w-3xl mx-4 sm:mx-6 p-6 relative max-h-[90vh] overflow-y-auto">
                <button @click="closeModal" class="absolute top-3 right-3 text-gray-500 hover:text-gray-800">
                    <i class="fa fa-xmark"></i>
                </button>

                <!-- LOADING -->
                <div v-if="isLoading" class="flex justify-center items-center h-48">
                    <i class="fa fa-spinner fa-spin text-3xl text-gray-600"></i>
                </div>

                <!-- VIEW TASK -->
                <div v-else-if="taskId && task && !isEditing">
                    <h2 class="text-xl font-semibold mb-4">{{ task.title }}</h2>
                    <p class="text-gray-600 mb-2">{{ task.subtitle }}</p>

                    <!-- Категории -->
                    <div v-if="task.categories?.length" class="mb-4">
                        <span
                            v-for="cat in task.categories"
                            :key="cat.id"
                            class="text-xs font-medium me-2 px-2.5 py-0.5 rounded-sm"
                            :style="{ backgroundColor: '#f3f4f6' }"
                        >
                            {{ cat.value }}
                        </span>
                    </div>

                    <!-- Фрагменты кода -->
                    <div v-if="hasCodeFragments" class="mb-4">
                        <h3 class="text-lg font-semibold mb-2">Примеры кода:</h3>
                        <div v-for="(frag, i) in parsedContent" :key="i" class="mb-3 border rounded p-3 bg-gray-50" v-highlight>
                            <div class="font-medium text-sm mb-1">📄 {{ frag.label }}</div>
                            <pre><code class="text-xs p-3 rounded overflow-x-auto">
{{ frag.content }}
    </code></pre>
                            <button @click="copyCode(frag.content)" class="mt-1 text-xs px-2 py-1 bg-gray-200 rounded hover:bg-gray-300">
                                Скопировать код
                            </button>
                        </div>
                    </div>

                    <!-- Файл для скачивания -->
                    <div v-if="task.file_path" class="mb-4">
                        <a
                            :href="task.file_path"
                            download
                            class="inline-flex items-center gap-2 px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700"
                        >
                            <i class="fa fa-download"></i> Скачать пример
                        </a>
                    </div>

                    <div v-if="isAdmin" class="mt-4 flex gap-2">
                        <button
                            @click="deleteTask"
                            class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 flex items-center gap-2"
                        >
                            <i class="fa fa-trash"></i> Удалить
                        </button>
                        <button
                            v-if="!isEditing"
                            @click="startEditing"
                            class="px-3 py-1 bg-yellow-500 text-white rounded hover:bg-yellow-600 flex items-center gap-2"
                        >
                            <i class="fa fa-pen"></i> Изменить
                        </button>
                    </div>
                </div>

                <!-- CREATE / EDIT TASK -->
                <div v-else>
                    <h2 class="text-xl font-semibold mb-4">{{
                            isEditing ? 'Редактировать задачу' : 'Создать задачу'
                        }}</h2>
                    <form @submit.prevent="saveTask">
                        <input
                            v-model="form.title"
                            type="text"
                            placeholder="Название"
                            class="w-full border p-2 rounded mb-3"
                        />

                        <input
                            v-model="form.subtitle"
                            type="text"
                            placeholder="Краткое описание"
                            class="w-full border p-2 rounded mb-3"
                        />

                        <select v-model="form.category_id" class="w-full border p-2 rounded mb-3">
                            <option disabled value="">Выберите категорию</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.value }}</option>
                        </select>

                        <div class="mb-3 flex items-center gap-2">
                            <input
                                id="hidden"
                                type="checkbox"
                                v-model="form.hidden"
                            />
                            <label for="hidden" class="text-sm">Скрыто</label>
                        </div>

                        <div v-for="(fragment, index) in form.fragments" :key="index"
                             class="mb-3 border p-2 rounded bg-gray-50">
                            <div class="flex justify-between items-center mb-1">
                                <input v-model="fragment.label" type="text"
                                       placeholder="Название файла, например class.php"
                                       class="border p-1 rounded w-1/2"/>
                                <button type="button" @click="removeFragment(index)"
                                        class="px-2 py-1 bg-red-500 text-white rounded hover:bg-red-600 text-sm">Удалить
                                </button>
                            </div>
                            <textarea v-model="fragment.content" rows="4" placeholder="Вставьте код сюда..."
                                      class="w-full border p-2 rounded font-mono text-sm"></textarea>
                        </div>

                        <button type="button" @click="addFragment"
                                class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 mb-3">Добавить
                            фрагмент
                        </button>

                        <div class="mb-3">
                            <label class="block mb-1 font-medium">Прикрепить файл</label>
                            <input type="file" @change="handleFile"/>
                            <div v-if="form.fileName" class="mt-1 text-sm text-gray-700">
                                Выбран файл: {{ form.fileName }}
                            </div>
                        </div>

                        <div class="flex justify-end mt-4 gap-2">
                            <button type="button" @click="closeModal" class="px-4 py-2 border rounded">Отмена</button>
                            <button type="submit"
                                    :disabled="!isFormValid"
                                    class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                Сохранить
                            </button>
                        </div>
                    </form>
                </div>

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
    emits: ["close", "deleted", "saved"],
    data() {
        return {
            task: null,
            isLoading: false,
            isEditing: false,
            categories: [],
            form: {
                title: "",
                subtitle: "",
                category_id: "",
                fragments: [],
                file: null,
                fileName: "",
                hidden: false
            }
        };
    },
    watch: {
        isOpen(newVal) {
            if (newVal) {
                if (this.taskId) {
                    this.isLoading = true;
                    this.fetchTask();
                } else {
                    this.resetForm();
                    this.fetchCategories();
                }
            }
        }
    },
    methods: {
        copyCode(code) {
            navigator.clipboard.writeText(code).then(() => {
                alert("Скопировано!");
            }).catch(() => {
                alert("Не удалось скопировать код");
            });
        },
        fetchCategories() {
            return fetch("/api/categories")
                .then(res => res.json())
                .then(data => this.categories = data)
                .catch(console.error);
        },
        fetchTask() {
            fetch(`/api/tasks/${this.taskId}`)
                .then(res => res.json())
                .then(data => this.task = data)
                .finally(() => this.isLoading = false);
        },
        resetForm() {
            this.form = {
                title: "",
                subtitle: "",
                category_id: "",
                fragments: [],
                file: null,
                fileName: "",
                hidden: false
            };
            this.isLoading = false;
        },
        addFragment() {
            this.form.fragments.push({label: "", content: ""});
        },
        removeFragment(index) {
            this.form.fragments.splice(index, 1);
        },
        async handleFile(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.form.fileName = file.name;

            const formData = new FormData();
            formData.append('file', file);

            const response = await fetch('/upload-file', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                credentials: 'same-origin'
            });

            const data = await response.json();
            this.form.file = data.url;
        },
        startEditing() {
            this.fetchCategories().then(() => {
                this.form.title = this.task.title;
                this.form.subtitle = this.task.subtitle;
                this.form.category_id = this.task.categories[0]?.id || '';
                this.form.fragments = this.parsedContent.map(f => ({...f}));
                this.form.file = this.task.file_path || null;
                this.form.hidden = Boolean(this.task.hidden);
                this.form.fileName = this.task.file_path ? this.task.file_path.split('/').pop() : '';
                this.isEditing = true;
            });
        },
        async saveTask() {
            const payload = {
                title: this.form.title,
                subtitle: this.form.subtitle,
                category_id: this.form.category_id,
                fragments: this.form.fragments,
                file: this.form.file,
                hidden: this.form.hidden
            };

            const url = this.isEditing ? `/tasks/${this.taskId}/update` : "/tasks";
            const method = this.isEditing ? "PUT" : "POST";

            try {
                const response = await fetch(url, {
                    method,
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(payload),
                    credentials: "same-origin"
                });

                if (!response.ok) throw await response.json();
                const savedTask = await response.json();
                this.$emit("saved", savedTask);
                this.closeModal();
            } catch (err) {
                alert(err.message || "Ошибка при сохранении задачи");
                console.error(err);
            }
        },
        async deleteTask() {
            if (!confirm("Вы точно хотите удалить задачу?")) return;
            try {
                const response = await fetch(`/tasks/${this.task.id}/delete`, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
                    }
                });
                if (!response.ok) throw await response.json();
                this.$emit("deleted", this.task.id);
                this.closeModal();
            } catch (err) {
                alert(err.message || "Ошибка удаления");
                console.error(err);
            }
        },
        closeModal() {
            this.isEditing = false;
            this.resetForm();
            this.task = null;
            this.isLoading = false;
            this.$emit("close");
        },
        clickOutside(e) {
            if (!this.$refs.modalContent.contains(e.target)) this.closeModal();
        }
    },
    computed: {
        parsedContent() {
            if (!this.task?.content) return [];
            try {
                return JSON.parse(this.task.content);
            } catch (e) {
                console.error("Ошибка парсинга content:", e);
                return [];
            }
        },
        hasCodeFragments() {
            return this.parsedContent.length > 0;
        },
        isFormValid() {
            return this.form.title && this.form.subtitle && this.form.category_id;
        }
    },
    directives: {
        highlight(el) {
            el.querySelectorAll("pre code").forEach(block => {
                hljs.highlightElement(block);
            });
        }
    }
};
</script>

<style scoped>
textarea {
    font-family: monospace;
    resize: vertical;
}
</style>
