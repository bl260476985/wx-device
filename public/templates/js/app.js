angular.module('app',[])
       .factory('FormService',['$q','$http',function($q,$http){
            // var API = 'http://47.104.84.255:18910'; //测试
            var API = 'http://admin.chargedot.com:18910'; //正式
            return{
                //维修报修表单
                repair:function(condition,pic1List,pic2List,pic3List){
                    return $q(function(resolve,reject){
                        var form = new FormData()
                        form.append('data',JSON.stringify(condition))
                        form.append('pic_appearance',pic1List)
                        form.append('pic_nameplate',pic2List)
                        form.append('pic_label',pic3List)
                        //API+'/api/formrepair/add',req,'headers':{'Content-Type': undefined},
                        $http({
                            method:'POST',
                            url:API+'/api/formrepair/add',
                            data:form,
                            headers : {
                                'Content-Type' :undefined  //angularjs设置文件上传的content-type修改方式
                            },
                        }).then(function(res){
                            if(res.data.code != 0){
                                return reject(res.data)
                            }
                            return resolve(res.data)
                        },function(res){
                            return reject(0)
                        })
                    })
                },
                //安装表单提交
                install:function(condition){
                    return $q(function(resolve,reject){
                        var req = {
                            data:JSON.stringify(condition)
                        }
                        $http.post(API+'/api/forminstall/add',req).then(function(res){
                            if(res.data.code != 0){
                                return reject(res.data)
                            }
                            return resolve(res.data)
                        },function(res){
                            return reject(0)
                        })
                    })
                },
                //加盟表单提交
                add:function(condition){
                    return $q(function(resolve,reject){
                        var req = {
                            data:JSON.stringify(condition)
                        }
                        $http.post(API+'/api/formappointment/add',req).then(function(res){
                            if(res.data.code != 0){
                                return reject(res.data)
                            }
                            return resolve(res.data)
                        },function(res){
                            return reject(0)
                        })
                    })
                },
                login:function(condition,sessionid,auth){
                    return $q(function(resolve,reject){
                        var req = {
                            content:JSON.stringify(condition)
                        }
                        $http.post('http://xiao.nbiotsg.com/v1/client/user/bind',req,{headers:{
                            'SESSIONID':sessionid,
                            'r':new Date().getTime(),
                            'Authorization':auth
                            }}).then(function(res){
                            if(res.data.code != 0){
                                return reject(res.data)
                            }
                            return resolve(res.data)
                        },function(res){
                            return reject(0)
                        })
                    })
                }
            }

       }])
       .factory('DialogService', ['$rootScope', '$timeout', function($rootScope, $timeout) {
            $rootScope.root = {
                msg: '',
                loading: false
            };
            return {
                alert: function(msg) {
                    $rootScope.root.msg = msg;
                    $('#AlertDialog').modal('show');

                },
                // confirm: function(msg, resolve) {
                //     $rootScope.root.msg = msg;
                //     $rootScope.root.confirm = resolve;
                //     $('#ConfirmDialog').modal('show');
                // },

                // loading: function(flag) {
                //     $rootScope.root.loading = flag;
                // }
            };
        }])
       //登录
       .controller('LoginCtrl',['$scope','$timeout','DialogService','FormService','$location',
        function($scope,$timeout,DialogService,FormService,$location){
            console.log($location.search())
            var binduser = $location.search().binduser,
                token = $location.search().token
            $scope.binduser = binduser;
            $scope.info = {
                name: $scope.binduser,
                pwd:''
            }
            $scope.submit = function() {
                if($scope.info.name === ''){
                    weui.alert('请输入用户名');
                    return false;
                }
                if($scope.info.pwd === ''){
                    weui.alert('请输入密码')
                    return false;
                }
                var base = new Base64();
                var name  = 'sirius',
                flag = new Date().getTime(),
                key = 'DzI3ZTkxODIXUzTzZjdhZXTlOTc8lPX7';
                let Sign = hex_md5( ('Sirius' + flag + key) );

                var $Auth = 'Basic' + ' ' + base.encode( name + ':'+ Sign );

                var date = new Date().getTime();
                var res = date + ':' + $scope.info.pwd
                var encodePwd = base.encode(res)
                var Pwd = ['System', encodePwd]
                $scope.info.pwd = Pwd.join(' ')
                var condition = {
                    name:$scope.info.name,
                    passwd:$scope.info.pwd,
                }
                FormService.login(condition,token,$Auth).then(function(res){
                    console.log(res)
                    weui.alert('绑定成功')
                    $scope.info = {
                        name: $scope.binduser,
                        pwd:''
                    }
                },function(res){
                    if(res.code != 0){
                        weui.alert(res.msg)
                    }
                })
            }
       }])