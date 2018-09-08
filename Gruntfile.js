module.exports = function(grunt) {
    require("load-grunt-tasks")(grunt);

    grunt.initConfig({
        pkg: grunt.file.readJSON("package.json"),
        ftpush: {
            beta: {
                auth: {
                    host: "wattyrev.com",
                    port: 21,
                    username: grunt.option("ftp-username"),
                    password: grunt.option("ftp-pass")
                },
                src: "./api",
                dest: "/api.beta.kitepaint.com",
                simple: false,
                useList: true
            },
            prod: {
                auth: {
                    host: "wattyrev.com",
                    port: 21,
                    username: grunt.option("ftp-username"),
                    password: grunt.option("ftp-pass")
                },
                src: "./api",
                dest: "/api.kitepaint.com",
                simple: false,
                useList: true
            }
        }
    });

    grunt.registerTask("deploy-beta", ["ftpush:beta"]);
    grunt.registerTask("deploy-prod", ["ftpush:prod"]);
};
