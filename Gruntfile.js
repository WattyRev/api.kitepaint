module.exports = function(grunt) {
    require("load-grunt-tasks")(grunt);

    grunt.initConfig({
        pkg: grunt.file.readJSON("package.json"),
        ftpush: {
            build: {
                auth: {
                    host: "wattyrev.com",
                    port: 21,
                    username: grunt.option("ftp-username"),
                    password: grunt.option("ftp-pass")
                },
                src: "./php",
                dest: "/beta.kitepaint.com/api",
                simple: false,
                useList: true
            }
        }
    });

    grunt.registerTask("deploy", ["ftpush"]);
};
