<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Task Management System</title>
    <link rel="stylesheet" href="./assets/css/app.css">
    <script defer src="https://cdn.jsdelivr.net/npm/vue@3.5.17/dist/vue.global.prod.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script defer src="./assets/js/app.js"></script>
</head>
<body>
    <!-- Vue root: one mount point to rule all the panels below. -->
    <div id="app" v-cloak>
        <!-- Hero/title area. -->
        <header class="hero">
            <h1>Task Management System</h1>
            <p>From chaos to color-coded chaos, but now with due dates.</p>
        </header>

        <!-- Logged-out mode: auth, verification, and password reset tools. -->
        <section v-if="!token" class="card auth-grid">
            <div>
                <h2>Login</h2>
                <input v-model="loginForm.email" placeholder="Email">
                <input v-model="loginForm.password" type="password" placeholder="Password">
                <button type="button" @click="login">Login</button>
            </div>
            <div>
                <h2>Register</h2>
                <input v-model="registerForm.name" placeholder="Name">
                <input v-model="registerForm.email" placeholder="Email">
                <input v-model="registerForm.password" type="password" placeholder="Password">
                <select v-model="registerForm.role">
                    <option value="team_member">Team Member</option>
                    <option value="project_manager">Project Manager</option>
                    <option value="admin">Admin</option>
                </select>
                <button type="button" @click="register">Register</button>
            </div>
            <div>
                <h2>Verify Email</h2>
                <input v-model="verifyToken" placeholder="Verification token">
                <button type="button" @click="verifyEmail">Verify</button>
            </div>
            <div>
                <h2>Reset Password</h2>
                <input v-model="resetEmail" placeholder="Account email">
                <button type="button" @click="requestReset">Request Reset Token</button>
                <input v-model="resetToken" placeholder="Reset token">
                <input v-model="newPassword" placeholder="New password" type="password">
                <button type="button" @click="resetPassword">Reset Password</button>
            </div>
        </section>

        <!-- Logged-in mode: dashboard, projects, board, and filters. -->
        <main v-else>
            <!-- Top toolbar with identity and common actions. -->
            <section class="card toolbar">
                <div>
                    <strong>{{ currentUser.name }}</strong>
                    <span class="badge">{{ currentUser.role }}</span>
                </div>
                <div>
                    <button @click="loadDashboard">Refresh Dashboard</button>
                    <button @click="logout">Logout</button>
                </div>
            </section>

            <!-- Snapshot stats + chart so progress feels real. -->
            <section class="card" v-if="dashboard">
                <h2>Dashboard</h2>
                <div class="stats-grid">
                    <article><h3>Projects</h3><p>{{ dashboard.stats.projects }}</p></article>
                    <article><h3>Open Tasks</h3><p>{{ dashboard.stats.tasks_open }}</p></article>
                    <article><h3>Overdue</h3><p>{{ dashboard.stats.tasks_overdue }}</p></article>
                    <article><h3>Done</h3><p>{{ dashboard.stats.tasks_done }}</p></article>
                </div>
                <div class="chart-wrap">
                    <canvas id="completionChart"></canvas>
                </div>
            </section>

            <!-- Project switcher and quick create form. -->
            <section class="card">
                <h2>Projects</h2>
                <div class="project-form">
                    <input v-model="projectForm.name" placeholder="Project name">
                    <select v-model="projectForm.visibility">
                        <option value="private">Private</option>
                        <option value="team">Team</option>
                        <option value="public">Public</option>
                    </select>
                    <button @click="createProject">Create Project</button>
                </div>
                <div class="project-list">
                    <button
                        v-for="project in projects"
                        :key="project.id"
                        @click="selectProject(project)"
                        :class="{ active: selectedProject && selectedProject.id === project.id }"
                    >
                        {{ project.name }}
                    </button>
                </div>
            </section>

            <!-- Kanban board for selected project. -->
            <section class="card" v-if="selectedProject">
                <h2>{{ selectedProject.name }} Board</h2>
                <div class="task-form">
                    <input v-model="taskForm.title" placeholder="Task title">
                    <input v-model="taskForm.description" placeholder="Short description">
                    <select v-model="taskForm.priority">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                    <input v-model="taskForm.due_date" type="datetime-local">
                    <button @click="createTask">Add Task</button>
                </div>

                <div class="columns">
                    <article class="column" v-for="column in columns" :key="column.key">
                        <h3>{{ column.label }}</h3>
                        <div class="task-dropzone" :data-column="column.key">
                            <div class="task-card" v-for="task in tasksByColumn(column.key)" :key="task.id" :data-task-id="task.id">
                                <strong>{{ task.title }}</strong>
                                <p>{{ task.description }}</p>
                                <small>{{ task.priority }} | {{ task.status }}</small>
                                <div class="task-actions">
                                    <button @click="openTask(task)">Details</button>
                                    <button @click="deleteTask(task.id)">Delete</button>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <!-- Task inspector with status/time/comments/activity. -->
            <section class="card" v-if="taskDetails">
                <h2>Task Details: {{ taskDetails.title }}</h2>
                <div class="task-detail-grid">
                    <label>Status
                        <select v-model="taskDetails.status" @change="saveTaskDetails">
                            <option value="not_started">Not Started</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="on_hold">On Hold</option>
                        </select>
                    </label>
                    <label>Tracked Minutes
                        <input type="number" v-model="taskDetails.tracked_minutes" @change="saveTaskDetails">
                    </label>
                    <label>Estimated Minutes
                        <input type="number" v-model="taskDetails.estimated_minutes" @change="saveTaskDetails">
                    </label>
                </div>
                <h3>Comments</h3>
                <div class="comment-list">
                    <p v-for="comment in comments" :key="comment.id">{{ comment.author_name }}: {{ comment.body }}</p>
                </div>
                <input v-model="newComment" placeholder="Type a comment and @mention someone if you dare">
                <button @click="addComment">Add Comment</button>

                <h3>Activity</h3>
                <ul class="activity-list">
                    <li v-for="entry in activity" :key="entry.id">{{ entry.actor_name }} - {{ entry.action }} - {{ entry.details }}</li>
                </ul>
            </section>

            <!-- Cross-project search panel with simple filters. -->
            <section class="card">
                <h2>Search Tasks</h2>
                <div class="search-grid">
                    <input v-model="filters.query" placeholder="Search by title/description">
                    <select v-model="filters.status">
                        <option value="">Any status</option>
                        <option value="not_started">Not Started</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="on_hold">On Hold</option>
                    </select>
                    <select v-model="filters.priority">
                        <option value="">Any priority</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                    <button @click="searchTasks">Search</button>
                </div>
                <ul>
                    <li v-for="task in searchResults" :key="task.id">{{ task.title }} ({{ task.priority }} / {{ task.status }})</li>
                </ul>
            </section>

        </main>

        <!-- Global toast-ish message area for success/error feedback. -->
        <p class="message" v-if="message">{{ message }}</p>
    </div>
</body>
</html>
