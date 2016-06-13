$(function() {
    var capitalizeFirstLetter = function(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }
    /*
     *
     * Tasks
     *
     */

    var zedxTasks = {
        total: 0, // number of total tasks
        completed: 0, // number of completed tasks
        percent: 0, // current achievement percent
        setTitle: function(title) {
            $('#build-task-title').html(title + ' <i class="fa fa-spinner fa-spin pull-right"></i>');
        },
        isComplete: function(taskNumber) {
            return zedxTasks.completed >= taskNumber;
        },
        autoProgress: function(taskNumber, estimatedTime, progressBar) {
            var percent = 0;

            var timerInterval = setInterval(function() {
                percent++;
              if (percent == 99 || zedxTasks.isComplete(taskNumber)) {
                clearInterval(timerInterval);
               }
              else{
                zedxTasks.progress(percent, progressBar);
              }
            }, estimatedTime/100);
        },
        progress: function(progressTask, progressBar) {
            progressBar.percent(progressTask);
            var taskPercent = Math.floor(100 / zedxTasks.total);

            var percent = zedxTasks.percent + Math.floor(Math.floor(progressTask) * taskPercent / 100);

            $('#zedx-total-building').val(percent).trigger('change');
        },
        completeTask: function(progressBar) {
            progressBar.percent(100);
            zedxTasks.completed++;
            zedxTasks.percent = Math.round(zedxTasks.completed * 100/zedxTasks.total);
            $('#zedx-total-building').val(zedxTasks.percent).trigger('change');
        },
        createProgressBar: function() {
            return $("#build-task-progress").Progress({
                percent: 0,
                width: $("#build-task").width(),
                height: 40,
                barColor:'#2C3E50',
                backgroundColor: '#EEEEEE',
                fontColor: '#2ECC71',
                fontSize: 26,
                increaseSpeed: 100
            });
        },
        startTask: function(id, title, url, estimatedTime) {
            var deferred = jQuery.Deferred();
            console.log("Starting new Task");

            zedxTasks.setTitle(title);
            var progressBar = zedxTasks.createProgressBar();
            var es = new EventSource(url);
            var nextTaskNumber = zedxTasks.completed + 1;

            if (estimatedTime) {
                zedxTasks.autoProgress(nextTaskNumber, estimatedTime, progressBar);
            }

            var progressHandler = function(e) {
                var result = JSON.parse( e.data );
                console.log("Task Sent an Event", result);
                zedxTasks.addLog(result.message);
                zedxTasks.progress(result.progress, progressBar);
            }

            var completeHandler = function(e) {
                var result = JSON.parse( e.data );
                zedxTasks.addLog('Please wait while installing ...');
                es.close();
                zedxTasks.completeTask(progressBar);
                console.log("Task Completed");
                deferred.resolve(result);
            }

            var errorHandler = function(e) {
                zedxTasks.addLog('Something going wrong ...');
                es.close();
                deferred.reject({
                    message: 'Something going wrong ...'
                });
            }

            es.addEventListener('progress', progressHandler, false);
            es.addEventListener('complete', completeHandler, false);
            es.addEventListener('error', errorHandler, false);

            return deferred.promise();
        },

        addLog: function(message) {
            $('#build-message').html(message);
        }
    }
    /*
     *
     * Installer
     *
     */
    var zedxInstaller = {
        steps: ['license', 'check', 'database', 'admin', 'settings', 'build', 'complete'],
        listToCheck: [
            'liveConnection', 'writePermission',
            'phpVersion', 'procOpen', 'pdoLibrary', 'mcryptLibrary',
            'mbstringLibrary', 'sslLibrary', 'gdLibrary',
            'curlLibrary', 'zipLibrary'
        ],
        errors: {},
        serializedData: {},
        streamRequest: function(title, estimatedTime) {
            var params = $.param(zedxInstaller.serializedData),
                id = zedxInstaller.serializedData.handler;

            return zedxTasks.startTask(id, title, "install.php?" + params, estimatedTime);
        },
        installRequest: function() {
            var deferred = jQuery.Deferred();

            $.post( "install.php", zedxInstaller.serializedData)
                .done(function(data) {
                    deferred.resolve(data);
                })
                .fail(function(data) {
                    deferred.reject(data);
                });

            return deferred.promise();
        },
        checkValidity: function(step) {
            return this.validation['isValidStep' + capitalizeFirstLetter(step)]();
        },
        execStep: function(step) {
            var stepName = step.slice(1);
            $('#' + stepName + '-fail').hide();
            return this.actions['on' + capitalizeFirstLetter(stepName) + 'Start']();
        },
        actions: {
            onCheckStart: function() {
                $('[data-type="check-code"]').hide();
                $('[data-type="check-code"]').removeClass("check-success check-error").addClass("check-waiting");
                $('[data-type="check-icon"]').removeClass().addClass("fa fa-spinner fa-spin fa-stack-1x");

                var nbrPassedCheck = 0;
                $.each(zedxInstaller.listToCheck, function(key, code) {
                    $('#code-' + code).animate({left:200, opacity:"show"}, key * 200 + 1000);

                    zedxInstaller.serializedData = {
                        handler: 'checkSystem',
                        code:code
                    }

                    zedxInstaller.installRequest()
                        .then(function(data){
                            nbrPassedCheck++;
                            $('#code-' + code).delay(500).queue(function(next){
                                $(this).removeClass('check-waiting').addClass('check-success');
                                $('#icon-' + code).removeClass('fa-spinner fa-spin').addClass('fa-check');
                                if (nbrPassedCheck == zedxInstaller.listToCheck.length) {
                                    $('[name="check"]').prop('disabled', false);
                                }
                                next();
                            });
                        }, function(data) {
                            $('#code-' + code).delay(500).queue(function(next){
                                $(this).removeClass('check-waiting').addClass('check-error');
                                $('#icon-' + code).removeClass('fa-spinner fa-spin').addClass('fa-remove');
                                $('#check-fail').show();
                                next();
                            });
                        });

                });
            },
            onDatabaseStart: function() {},
            onAdminStart: function() {},
            onSettingsStart: function() {},
            onBuildStart: function() {
                var serializedData = $("#installer-form").serializeArray();
                $.each(serializedData, function(key, el){
                    zedxInstaller.serializedData[el.name] = el.value;
                });

                zedxTasks.total = Object.keys(zedxInstaller.process).length;

                setTimeout(function() {
                    zedxInstaller.process.downloadLatestVersion();
                }, 500);

            },
            onCompleteStart: function() {
                var pathname = window.location.pathname,
                    dir = pathname.substring(0, pathname.lastIndexOf('/')),
                    baseUrl = window.location.protocol + '//' + window.location.host + dir;

                var adminUrl = baseUrl + '/zxadmin';

                $('#complete-baseUrl').html('<a href="' + baseUrl + '">' + baseUrl + '</a>');
                $('#complete-adminUrl').html('<a href="' + adminUrl + '">' + adminUrl + '</a>');
            }
        },
        process: {
            downloadLatestVersion: function() {
                zedxInstaller.serializedData.handler = 'downloadLatestVersion';
                var title = "Downloading Latest ZEDx Version";
                zedxInstaller.streamRequest(title).then(function() {
                    console.log('downloadLatestVersion OK');
                    zedxInstaller.process.extractCore();
                }, function() {
                    console.log('downloadLatestVersion Error');
                });
            },
            extractCore: function() {
                zedxInstaller.serializedData.handler = 'extractCore';
                var title = 'Extracting ZEDx';
                zedxInstaller.streamRequest(title, 100000).then(function() {
                    console.log('extractCore OK');
                    zedxInstaller.process.changePermissions();
                }, function() {
                    console.log('extractCore Error');
                });
            },
            changePermissions: function() {
                zedxInstaller.serializedData.handler = 'changePermissions';
                var title = 'Changing Files/Folders permissions';
                zedxInstaller.streamRequest(title, 20000).then(function() {
                    console.log('changePermissions OK');
                    zedxInstaller.process.buildConfigs();
                }, function() {
                    console.log('changePermissions Error');
                });
            },
            buildConfigs: function() {
                zedxInstaller.serializedData.handler = 'buildConfigs';
                var title = 'Configuration';
                zedxInstaller.streamRequest(title, 20000).then(function() {
                    console.log('buildConfigs OK');
                    zedxInstaller.process.migrateDatabase();
                }, function() {
                    console.log('buildConfigs Error');
                });
            },
            migrateDatabase: function() {
                zedxInstaller.serializedData.handler = 'migrateDatabase';
                var title = 'Preparing database';
                zedxInstaller.streamRequest(title, 100000).then(function() {
                    console.log('migrateDatabase OK');
                    zedxInstaller.process.createAdminAccount();
                }, function() {
                    console.log('migrateDatabase Error');
                });
            },
            createAdminAccount: function() {
                zedxInstaller.serializedData.handler = 'createAdminAccount';
                var title = 'Creating Admin account';
                zedxInstaller.streamRequest(title, 20000).then(function() {
                    console.log('createAdminAccount OK');
                    zedxInstaller.process.createSetting();
                }, function() {
                    console.log('createAdminAccount Error');
                });
            },
            createSetting: function() {
                zedxInstaller.serializedData.handler = 'createSetting';
                var title = 'Apply settings';
                zedxInstaller.streamRequest(title, 20000).then(function() {
                    console.log('createSetting OK');
                    zedxInstaller.process.setDefaultTheme();
                }, function() {
                    console.log('createSetting Error');
                });
            },
            setDefaultTheme: function() {
                zedxInstaller.serializedData.handler = 'setDefaultTheme';
                var title = 'Setting default theme';
                zedxInstaller.streamRequest(title, 20000).then(function() {
                    console.log('setDefaultTheme OK');
                    zedxInstaller.process.createSymLinks();
                }, function() {
                    console.log('setDefaultTheme Error');
                });
            },
            createSymLinks: function() {
                zedxInstaller.serializedData.handler = 'createSymLinks';
                var title = 'Creating symlinks';
                zedxInstaller.streamRequest(title, 1000).then(function() {
                    console.log('createSymLinks OK');
                    zedxInstaller.process.clearAll();
                }, function() {
                    console.log('createSymLinks Error');
                });
            },
            clearAll: function() {
                zedxInstaller.serializedData.handler = 'clearAll';
                var title = 'Clear Installation files';
                zedxInstaller.streamRequest(title, 1000).then(function() {
                    console.log('clearAll OK');
                    // Go to next
                    var tabname = '#complete';
                    $('ul.nav li a[href="' + tabname + '"]').parent().removeClass('disabled');
                    $('ul.nav li a[href="' + tabname + '"]').trigger('click');
                    zedxInstaller.execStep(tabname);
                }, function() {
                    console.log('clearAll Error');
                });
            }
        },
        validation: {
            isValidStepLicense: function() {
                var deferred = jQuery.Deferred();
                deferred.resolve();
                return deferred.promise();
            },
            isValidStepCheck: function() {
                var deferred = jQuery.Deferred();
                deferred.resolve();
                return deferred.promise();
            },
            isValidStepDatabase: function() {
                var deferred = jQuery.Deferred();

                zedxInstaller.serializedData = {
                    handler: 'checkDatabase'
                };

                var elements = [
                    'db_type', 'db_host', 'db_port', 'db_name',
                    'db_username', 'db_password', 'db_prefix'
                ];

                $.each(elements, function(key, el) {
                    zedxInstaller.serializedData[el] = $('#' + el).val();
                });


                zedxInstaller.installRequest().then(
                    function(data) {
                        deferred.resolve();
                    },
                    function(data) {
                        deferred.reject({
                            title:"Database fail",
                            error: data.responseText
                        });
                    }
                );

                return deferred.promise();
            },
            isValidStepAdmin: function() {
                var deferred = jQuery.Deferred();

                zedxInstaller.serializedData = {
                    handler: 'checkAdmin'
                };

                var elements = [
                    'admin_email', 'admin_name', 'admin_password',
                    'admin_password_confirmation'
                ];

                $.each(elements, function(key, el) {
                    zedxInstaller.serializedData[el] = $('#' + el).val();
                });

                zedxInstaller.installRequest().then(
                    function(data) {
                        deferred.resolve();
                    },
                    function(data) {
                        deferred.reject({
                            title:"Admin fail",
                            error: data.responseText
                        });
                    }
                );

                return deferred.promise();
            },
            isValidStepSettings: function() {
                var deferred = jQuery.Deferred();

                zedxInstaller.serializedData = {
                    handler: 'checkSettings'
                };

                var elements = [
                    'website_name', 'website_description',
                ];

                $.each(elements, function(key, el) {
                    zedxInstaller.serializedData[el] = $('#' + el).val();
                });

                zedxInstaller.installRequest().then(
                    function(data) {
                        deferred.resolve();
                    },
                    function(data) {
                        deferred.reject({
                            title:"Setting fail",
                            error: data.responseText
                        });
                    }
                );

                return deferred.promise();
            }
        }

    }

    $('body').tooltip({
        selector: '[data-tooltip="tooltip"]'
    });


    $(document).on('click', '.btn-next', function(e) {
        var stepName = $(this).attr('name');
        var tabname = $(this).attr('href');
        e.preventDefault();
        zedxInstaller.checkValidity(stepName).then(function(){
            $('ul.nav li a[href="' + tabname + '"]').parent().removeClass('disabled');
            $('ul.nav li a[href="' + tabname + '"]').trigger('click');
            zedxInstaller.execStep(tabname);
        }, function(data) {
            $('#' + stepName + '-fail').show();
            $('#' + stepName + '-fail').html(Mustache.to_html($("#template_fail").html(), data));
        });
    });

    $(document).on('click', '.btn-retry', function(e) {
        zedxInstaller.execStep("#check");
    });

    $('ul.nav li').on('click', function(e) {
        if ($(this).hasClass('disabled')) {
            e.preventDefault();
            return false;
        }
    });

    $.each(zedxInstaller.steps, function(key, step) {
        $('#' + step).html(Mustache.to_html($("#template_" + step).html()));
    });

    $('#db_type').on('change', function() {
        var databaseType = $(this).val();
        $('#database_config_data').html(Mustache.to_html($("#template_database_" + databaseType).html()));
    });

    $('#db_type').trigger('change');

    $(document).on('submit','#installer-form',function(e){
        var data = $(this).serializeArray();
        e.preventDefault();
    });

    $(".knob").knob({
        format : function (value) {
            return value + '%';
        }
    });
});
