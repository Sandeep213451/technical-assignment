var app = angular.module('taskApp', ['ngRoute']);

app.config(function($routeProvider, $httpProvider) {
    $routeProvider
    .when("/", {
        templateUrl : "login.html",
        controller : "AuthController"
    })
    .when("/login", {
        templateUrl : "login.html",
        controller : "AuthController"
    })
    .when("/register", {
        templateUrl : "register.html",
        controller : "AuthController"
    })
    .when("/dashboard", {
        templateUrl : "dashboard.html",
        controller : "DashboardController"
    })
    .when("/create-task", {
        templateUrl : "create-task.html",
        controller : "TaskController"
    })
    .when("/profile", {
        templateUrl : "profile.html",
        controller : "ProfileController"
    })
    .otherwise({
        redirectTo: "/login"
    });

    // Send bearer token with every HTTP request
    $httpProvider.interceptors.push(function($window) {
        return {
            request: function(config) {
                var token = $window.localStorage.getItem('token');
                if (token) {
                    config.headers.Authorization = 'Bearer ' + token;
                }
                config.headers['Accept'] = 'application/json';
                return config;
            }
        };
    });
});

app.service('AuthService', function($http, $window, $rootScope) {
    var baseUrl = '/api';

    this.login = function(credentials) {
        return $http.post(baseUrl + '/login', credentials);
    };

    this.register = function(userData) {
        return $http.post(baseUrl + '/register', userData);
    };

    this.logout = function() {
        return $http.post(baseUrl + '/logout').finally(function() {
            $window.localStorage.removeItem('token');
            $window.localStorage.removeItem('user');
            $rootScope.currentUser = null;
        });
    };

    this.getProfile = function() {
        return $http.get(baseUrl + '/user/profile');
    };

    this.updateProfile = function(profileData) {
        return $http.put(baseUrl + '/user/profile', profileData);
    };

    this.getUser = function() {
        var user = $window.localStorage.getItem('user');
        if(user) {
            return JSON.parse(user);
        }
        return null;
    };

    this.setUser = function(user) {
        $window.localStorage.setItem('user', JSON.stringify(user));
        $rootScope.currentUser = user;
    };

    this.isLoggedIn = function() {
        return $window.localStorage.getItem('token') !== null;
    };
});

app.controller('NavController', function($scope, $location, AuthService) {
    $scope.isLoggedIn = AuthService.isLoggedIn;
    
    $scope.isAdmin = function() {
        var user = AuthService.getUser();
        return user && (user.role === 'Admin' || user.role === 'Manager');
    };

    $scope.getUser = AuthService.getUser;

    $scope.logout = function() {
        AuthService.logout();
        $location.path('/login');
    };
});

app.controller('AuthController', function($scope, $location, $window, $rootScope, AuthService) {
    if (AuthService.isLoggedIn()) {
        $location.path('/dashboard');
    }

    $scope.user = {
        role: 'User',
        department: 'IT',
        years_of_experience: 1,
        location: 'Bhubaneswar'
    };
    $scope.error = '';

    $scope.login = function() {
        $scope.error = '';
        AuthService.login($scope.user).then(function(response) {
            $window.localStorage.setItem('token', response.data.token);
            AuthService.setUser(response.data.user);
            $location.path('/dashboard');
        }, function(error) {
            $scope.error = (error.data && error.data.message) ? error.data.message : 'Invalid login credentials.';
        });
    };

    $scope.register = function() {
        $scope.error = '';
        AuthService.register($scope.user).then(function(response) {
            $window.localStorage.setItem('token', response.data.token);
            AuthService.setUser(response.data.user);
            $location.path('/dashboard');
        }, function(error) {
            $scope.error = (error.data && error.data.message) ? error.data.message : 'Registration failed. Check inputs.';
        });
    };
});

app.service('TaskService', function($http) {
    var baseUrl = '/api/tasks';

    this.getAllTasks = function() {
        return $http.get(baseUrl);
    };

    this.getMyEligibleTasks = function() {
        return $http.get('/api/my-eligible-tasks');
    };

    this.createTask = function(taskData) {
        return $http.post(baseUrl, taskData);
    };

    this.updateTaskStatus = function(taskId, status) {
        return $http.patch(baseUrl + '/' + taskId + '/status', { status: status });
    };

    this.deleteTask = function(taskId) {
        return $http.delete(baseUrl + '/' + taskId);
    };

    this.recomputeEligibility = function(taskId) {
        return $http.post(baseUrl + '/recompute-eligibility', { task_id: taskId });
    };
});

app.controller('DashboardController', function($scope, TaskService, AuthService, $location) {
    if (!AuthService.isLoggedIn()) {
        $location.path('/login');
        return;
    }

    $scope.currentUser = AuthService.getUser();
    $scope.isAdmin = function() {
        return $scope.currentUser && ($scope.currentUser.role === 'Admin' || $scope.currentUser.role === 'Manager');
    };

    $scope.allTasks = [];
    $scope.myTasks = [];
    $scope.message = '';
    $scope.error = '';
    $scope.loading = true;

    $scope.loadDashboard = function() {
        $scope.loading = true;
        $scope.error = '';
        
        AuthService.getProfile().then(function(res) {
            $scope.currentUser = res.data;
            AuthService.setUser(res.data);
        }).catch(function() {
            $scope.error = 'Failed to load profile.';
        });

        if ($scope.isAdmin()) {
            TaskService.getAllTasks().then(function(response) {
                $scope.allTasks = response.data;
            }).catch(function(err) {
                $scope.error = 'Failed to load tasks.';
            }).finally(function() {
                $scope.loading = false;
            });
        } else {
            TaskService.getMyEligibleTasks().then(function(response) {
                $scope.myTasks = response.data;
            }).catch(function(err) {
                $scope.error = 'Failed to load eligible tasks.';
            }).finally(function() {
                $scope.loading = false;
            });
        }
    };

    $scope.recompute = function(taskId) {
        $scope.loading = true;
        TaskService.recomputeEligibility(taskId).then(function(res) {
            $scope.message = 'Rule evaluation recomputed for Task #' + taskId;
            setTimeout(function() {
                $scope.loadDashboard();
            }, 1000);
        }).catch(function() {
            $scope.error = 'Failed to recompute eligibility.';
            $scope.loading = false;
        });
    };

    $scope.updateTaskStatus = function(taskId, newStatus) {
        $scope.loading = true;
        TaskService.updateTaskStatus(taskId, newStatus).then(function(res) {
            $scope.message = 'Task #' + taskId + ' status updated to ' + newStatus;
            $scope.loadDashboard();
        }).catch(function() {
            $scope.error = 'Failed to update task status.';
            $scope.loading = false;
        });
    };

    $scope.deleteTask = function(taskId) {
        if (!confirm('Are you sure you want to delete Task #' + taskId + '?')) return;
        $scope.loading = true;
        TaskService.deleteTask(taskId).then(function() {
            $scope.message = 'Task deleted successfully.';
            $scope.loadDashboard();
        }).catch(function() {
            $scope.error = 'Failed to delete task.';
            $scope.loading = false;
        });
    };

    $scope.loadDashboard();
});

app.controller('TaskController', function($scope, TaskService, $location, AuthService) {
    var user = AuthService.getUser();
    if (!AuthService.isLoggedIn() || (user.role !== 'Admin' && user.role !== 'Manager')) {
        $location.path('/dashboard');
        return;
    }

    $scope.task = {
        title: '',
        description: '',
        priority: 'Medium',
        due_date: '',
        rules: {
            department: '',
            min_experience: null,
            max_active_tasks: null,
            location: ''
        }
    };
    $scope.message = '';
    $scope.error = '';
    $scope.isSubmitting = false;

    $scope.createTask = function() {
        $scope.message = '';
        $scope.error = '';
        
        if (!$scope.task.title || !$scope.task.description) {
            $scope.error = 'Title and description are required.';
            return;
        }

        $scope.isSubmitting = true;
        TaskService.createTask($scope.task).then(function(response) {
            $scope.message = 'Task created successfully! Background rule engine dispatched.';
            $scope.task = {
                title: '',
                description: '',
                priority: 'Medium',
                due_date: '',
                rules: { department: '', min_experience: null, max_active_tasks: null, location: '' }
            };
        }).catch(function(error) {
            $scope.error = (error.data && error.data.message) ? error.data.message : 'Error creating task';
        }).finally(function() {
            $scope.isSubmitting = false;
        });
    };
});

app.controller('ProfileController', function($scope, AuthService, $location) {
    if (!AuthService.isLoggedIn()) {
        $location.path('/login');
        return;
    }

    $scope.profile = {};
    $scope.message = '';
    $scope.error = '';

    AuthService.getProfile().then(function(res) {
        $scope.profile = res.data;
    });

    $scope.saveProfile = function() {
        $scope.message = '';
        $scope.error = '';
        AuthService.updateProfile($scope.profile).then(function(res) {
            $scope.message = res.data.message;
            AuthService.setUser(res.data.user);
        }, function(err) {
            $scope.error = 'Failed to update profile.';
        });
    };
});
