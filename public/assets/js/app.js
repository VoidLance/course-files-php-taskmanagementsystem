const { createApp, nextTick } = Vue;

const currentPath = window.location.pathname;
// Build API base from current URL so moving folders doesn't brick the app.
const appBasePath = currentPath.replace(/\/index\.php$/, '').replace(/\/$/, '');

createApp({
    data() {
        return {
            apiBase: `${appBasePath}/api.php/api/v1`,
            token: localStorage.getItem('tms_token') || '',
            currentUser: JSON.parse(localStorage.getItem('tms_user') || '{}'),
            message: '',
            loginForm: { email: '', password: '' },
            registerForm: { name: '', email: '', password: '', role: 'team_member' },
            verifyToken: '',
            resetEmail: '',
            resetToken: '',
            newPassword: '',
            projectForm: { name: '', visibility: 'private' },
            projects: [],
            selectedProject: null,
            taskForm: {
                title: '',
                description: '',
                priority: 'medium',
                due_date: '',
            },
            tasks: [],
            columns: [
                { key: 'todo', label: 'To Do' },
                { key: 'in_progress', label: 'In Progress' },
                { key: 'done', label: 'Done' },
            ],
            taskDetails: null,
            comments: [],
            activity: [],
            newComment: '',
            filters: { query: '', status: '', priority: '' },
            searchResults: [],
            dashboard: null,
            chart: null,
        };
    },
    mounted() {
        // If token exists, skip the awkward login reunion.
        if (this.token) {
            this.bootAuthenticatedArea();
        }
    },
    methods: {
        async api(path, options = {}) {
            // Central fetch helper so we only fight headers and auth tokens once.
            const headers = {
                'Content-Type': 'application/json',
                ...(options.headers || {}),
            };

            if (this.token) {
                headers.Authorization = `Bearer ${this.token}`;
            }

            let response;
            try {
                response = await fetch(`${this.apiBase}${path}`, {
                    ...options,
                    headers,
                });
            } catch (error) {
                throw new Error('Network error: unable to reach API server');
            }

            if (response.headers.get('content-type')?.includes('text/csv')) {
                return response.text();
            }

            const rawText = await response.text();
            let json = {};
            if (rawText) {
                try {
                    json = JSON.parse(rawText);
                } catch {
                    json = { error: rawText.slice(0, 220) };
                }
            }
            if (!response.ok) {
                throw new Error(json.error || `Request failed (${response.status})`);
            }

            return json;
        },
        setMessage(text) {
            // Snack-bar style message: loud enough to help, short enough to ignore later.
            this.message = text;
            setTimeout(() => {
                this.message = '';
            }, 3500);
        },
        async register() {
            try {
                await this.api('/auth/register', {
                    method: 'POST',
                    body: JSON.stringify(this.registerForm),
                });
                this.setMessage('Registered. Check storage/logs/mail.log for tokens. Glamorous, right?');
            } catch (error) {
                this.setMessage(error.message);
            }
        },
        async verifyEmail() {
            try {
                await this.api('/auth/verify-email', {
                    method: 'POST',
                    body: JSON.stringify({ token: this.verifyToken }),
                });
                this.setMessage('Email verified. Bureaucracy completed.');
            } catch (error) {
                this.setMessage(error.message);
            }
        },
        async login() {
            try {
                const payload = await this.api('/auth/login', {
                    method: 'POST',
                    body: JSON.stringify(this.loginForm),
                });

                this.token = payload.token;
                this.currentUser = payload.user;
                localStorage.setItem('tms_token', payload.token);
                localStorage.setItem('tms_user', JSON.stringify(payload.user));

                this.bootAuthenticatedArea();
                this.setMessage('Logged in successfully. Productivity unlocked.');
            } catch (error) {
                this.setMessage(error.message);
            }
        },
        logout() {
            this.token = '';
            this.currentUser = {};
            this.projects = [];
            this.tasks = [];
            this.selectedProject = null;
            localStorage.removeItem('tms_token');
            localStorage.removeItem('tms_user');
            this.setMessage('Logged out. Your tasks miss you already.');
        },
        async requestReset() {
            try {
                await this.api('/auth/password/request-reset', {
                    method: 'POST',
                    body: JSON.stringify({ email: this.resetEmail }),
                });
                this.setMessage('Reset request submitted. Check the mail log for token.');
            } catch (error) {
                this.setMessage(error.message);
            }
        },
        async resetPassword() {
            try {
                await this.api('/auth/password/reset', {
                    method: 'POST',
                    body: JSON.stringify({ token: this.resetToken, password: this.newPassword }),
                });
                this.setMessage('Password updated. The vault is sealed again.');
            } catch (error) {
                this.setMessage(error.message);
            }
        },
        async bootAuthenticatedArea() {
            // Quick boot sequence: projects first, then pretty dashboard stats.
            await this.loadProjects();
            await this.loadDashboard();
        },
        async loadProjects() {
            try {
                const payload = await this.api('/projects');
                this.projects = payload.projects;
                if (!this.selectedProject && this.projects.length > 0) {
                    await this.selectProject(this.projects[0]);
                }
            } catch (error) {
                this.setMessage(error.message);
            }
        },
        async createProject() {
            try {
                await this.api('/projects', {
                    method: 'POST',
                    body: JSON.stringify(this.projectForm),
                });

                this.projectForm = { name: '', visibility: 'private' };
                await this.loadProjects();
                this.setMessage('Project created. Another board enters the arena.');
            } catch (error) {
                this.setMessage(error.message);
            }
        },
        async selectProject(project) {
            // Load data, then wire drag/drop after DOM actually exists.
            this.selectedProject = project;
            await this.loadTasks();
            await nextTick();
            this.enableDragDrop();
        },
        async loadTasks() {
            if (!this.selectedProject) {
                return;
            }

            try {
                const payload = await this.api(`/projects/${this.selectedProject.id}/tasks`);
                this.tasks = payload.tasks;
            } catch (error) {
                this.setMessage(error.message);
            }
        },
        tasksByColumn(column) {
            // Legacy-safe fallback: tasks with blank column go to todo.
            return this.tasks.filter((task) => task.column_name === column || (column === 'todo' && !task.column_name));
        },
        async createTask() {
            if (!this.selectedProject) {
                this.setMessage('Pick a project first. Psychic APIs are still in beta.');
                return;
            }

            try {
                await this.api(`/projects/${this.selectedProject.id}/tasks`, {
                    method: 'POST',
                    body: JSON.stringify({
                        ...this.taskForm,
                        column_name: 'todo',
                        status: 'not_started',
                    }),
                });

                this.taskForm = { title: '', description: '', priority: 'medium', due_date: '' };
                await this.loadTasks();
                this.setMessage('Task created. Backlog intensity increased.');
            } catch (error) {
                this.setMessage(error.message);
            }
        },
        enableDragDrop() {
            // SortableJS does the heavy lifting while we pretend this was easy all along.
            document.querySelectorAll('.task-dropzone').forEach((dropzone) => {
                if (dropzone.dataset.sortableBound === '1') {
                    return;
                }

                dropzone.dataset.sortableBound = '1';
                Sortable.create(dropzone, {
                    group: 'board',
                    animation: 170,
                    onEnd: async (event) => {
                        const taskId = Number(event.item.dataset.taskId);
                        const columnName = event.to.dataset.column;

                        try {
                            await this.api(`/tasks/${taskId}/move`, {
                                method: 'PATCH',
                                body: JSON.stringify({
                                    column_name: columnName,
                                    position: event.newIndex,
                                }),
                            });

                            await this.loadTasks();
                        } catch (error) {
                            this.setMessage(error.message);
                        }
                    },
                });
            });
        },
        async openTask(task) {
            // Open side details and immediately fetch conversation/history.
            this.taskDetails = { ...task };
            await this.loadComments();
            await this.loadActivity();
        },
        async saveTaskDetails() {
            if (!this.taskDetails) {
                return;
            }

            try {
                await this.api(`/tasks/${this.taskDetails.id}`, {
                    method: 'PATCH',
                    body: JSON.stringify(this.taskDetails),
                });
                await this.loadTasks();
                this.setMessage('Task updated. Tiny victory achieved.');
            } catch (error) {
                this.setMessage(error.message);
            }
        },
        async deleteTask(taskId) {
            try {
                await this.api(`/tasks/${taskId}`, { method: 'DELETE' });
                await this.loadTasks();
                if (this.taskDetails?.id === taskId) {
                    this.taskDetails = null;
                    this.comments = [];
                    this.activity = [];
                }
                this.setMessage('Task deleted. It had a good run.');
            } catch (error) {
                this.setMessage(error.message);
            }
        },
        async loadComments() {
            if (!this.taskDetails) {
                return;
            }

            try {
                const payload = await this.api(`/tasks/${this.taskDetails.id}/comments`);
                this.comments = payload.comments;
            } catch (error) {
                this.setMessage(error.message);
            }
        },
        async addComment() {
            if (!this.taskDetails || !this.newComment.trim()) {
                return;
            }

            try {
                await this.api(`/tasks/${this.taskDetails.id}/comments`, {
                    method: 'POST',
                    body: JSON.stringify({ body: this.newComment }),
                });
                this.newComment = '';
                await this.loadComments();
                await this.loadActivity();
            } catch (error) {
                this.setMessage(error.message);
            }
        },
        async loadActivity() {
            if (!this.taskDetails) {
                return;
            }

            try {
                const payload = await this.api(`/tasks/${this.taskDetails.id}/activity`);
                this.activity = payload.activity;
            } catch (error) {
                this.setMessage(error.message);
            }
        },
        async searchTasks() {
            // Keep query string clean by only sending filters that are actually used.
            const params = new URLSearchParams();
            if (this.filters.query) params.set('query', this.filters.query);
            if (this.filters.status) params.set('status', this.filters.status);
            if (this.filters.priority) params.set('priority', this.filters.priority);

            try {
                const payload = await this.api(`/search/tasks?${params.toString()}`);
                this.searchResults = payload.tasks;
            } catch (error) {
                this.setMessage(error.message);
            }
        },
        async loadDashboard() {
            try {
                const payload = await this.api('/reports/dashboard');
                this.dashboard = payload;
                this.renderChart();
            } catch (error) {
                this.setMessage(error.message);
            }
        },
        renderChart() {
            if (!this.dashboard) {
                return;
            }

            // If stats are boring, at least let the line chart look productive.
            const canvas = document.getElementById('completionChart');
            if (!canvas) {
                return;
            }

            if (this.chart) {
                this.chart.destroy();
            }

            this.chart = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: this.dashboard.completion_trend.map((entry) => entry.day),
                    datasets: [{
                        label: 'Completed tasks per day',
                        data: this.dashboard.completion_trend.map((entry) => Number(entry.completed)),
                        borderColor: '#2f6f80',
                        backgroundColor: 'rgba(47, 111, 128, 0.2)',
                        borderWidth: 2,
                        tension: 0.28,
                        fill: true,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 2.2,
                },
            });
        },
    },
}).mount('#app');
