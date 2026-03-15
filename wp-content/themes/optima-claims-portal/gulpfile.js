/**************************
 * Gulpfile Dependencies
 **************************/

const { array } = require("yargs");

let gulp = require("gulp"),
  gulpIf = require("gulp-if"),
  rename = require("gulp-rename"),
  browserSync = require("browser-sync").create(),
  argv = require("yargs").argv,
  fs = require('fs'),
  path = require('path'),
  plumber = require("gulp-plumber"),
  // CSS plugins
  postcss = require("gulp-postcss"),
  cssImport = require("postcss-import"),
  tailwindcss = require("tailwindcss"),
  nested = require("postcss-nested"),
  cssvars = require("postcss-simple-vars"),
  // CSS plugins used in production
  autoprefixer = require("autoprefixer"),
  cssnano = require("cssnano"),
  nodeResolve = require("@rollup/plugin-node-resolve"),
  commonjs = require("@rollup/plugin-commonjs"),
  glob = require('glob'),
  { rollup } = require('rollup'),
  virtual = require('@rollup/plugin-virtual'),
  terser = require('@rollup/plugin-terser');


/**************************
 * Task Styles
 **************************/
gulp.task("styles", function () {
  return gulp
    .src("_assets/styles/main.css")
    .pipe(
      plumber({
        errorHandler: function (err) {
          console.error("\nCSS Error:\n", err.message);
          this.emit("end");
        },
      })
    )
    .pipe(postcss([cssImport, tailwindcss, nested, cssvars]))
    .pipe(gulpIf(argv.production, postcss([autoprefixer, cssnano])))
    .pipe(rename("main.min.css"))
    .pipe(gulp.dest("dist/"))
    .pipe(browserSync.reload({ stream: true }));
});


/**************************
 * Scripts using rollup.js
 **************************/

var cache;

// Core scripts
const { pathToFileURL, fileURLToPath } = require("url");

function fileUrlResolver() {
  return {
    name: "file-url-resolver",
    resolveId(source) {
      if (typeof source === "string" && source.startsWith("file://")) {
        return fileURLToPath(source); // turns file:///C:/... into C:\... (or C:/...)
      }
      return null;
    },
  };
}

gulp.task("core-scripts", async function () {
  const coreFiles = glob.sync("_assets/js/core/*.js");

  const virtualEntryCode = coreFiles
    .map((f) => {
      const abs = path.resolve(f);
      const href = pathToFileURL(abs).href; // file:///C:/...
      return `import ${JSON.stringify(href)};`;
    })
    .join("\n");

  const plugins = [
    virtual({ "virtual-entry.js": virtualEntryCode }),
    fileUrlResolver(),        // <-- IMPORTANT: must come before nodeResolve
    nodeResolve(),
    commonjs(),
    ...(argv.production ? [terser()] : []),
  ];

  const bundle = await rollup({
    input: "virtual-entry.js",
    plugins,
    cache: cache,
  });

  cache = bundle;

  await bundle.write({
    file: "dist/production-dist.js",
    format: "iife",
    sourcemap: false,
    name: "output",
  });

  browserSync.reload();
});


/**************************
 * Addon Scripts
 **************************/
var addonScripts = fs.readdirSync('_assets/js/addon/');
var jsFiles = [];

// collect only JS files
addonScripts.forEach(function(script){  
  if(path.extname(script).toLowerCase() === ".js") {
    jsFiles.push(script);
  }
});

// create gulp tasks for each addon
jsFiles.forEach(function(script){
  gulp.task(script, async function () {
    const bundle = await rollup({
      input: "_assets/js/addon/" + script,
      plugins: [
        nodeResolve(),
        commonjs(),
        (argv.production ? terser() : null) // Add this line to minify the output
      ],
      cache: script,
    });

    cache = bundle;

    await bundle.write({
      file: `dist/production-${script}`,
      format: 'iife',
      sourcemap: false,
      name: 'output',
    });

    browserSync.reload();
  });
});



/**************************
 * Task Watch
 **************************/
gulp.task("watch", () => {
  gulp.watch(['_assets/styles/**/*.css', '_views/**/*.twig'], gulp.series("styles"));
  gulp.watch(`_assets/js/core/*.js`, gulp.series("core-scripts"));
  
  jsFiles.forEach(function(script){
    gulp.watch(`_assets/js/addon/` + script, gulp.series(script));
  });

});


/**************************
 * Task Serve
 **************************/
gulp.task("serve", () => {
  browserSync.init({
    proxy: `adtrak-boilerplate.vm`,
    files: ["**/*.php", "**/*.js", "**/*.twig", "**/*.css"],
    ghostMode: false,
    open: false,
    notify: false
  });
});


/**************************
 * Gulp Automation
 **************************/
gulp.task("default", gulp.parallel("styles", "core-scripts", jsFiles, "watch", "serve"));
gulp.task("build", gulp.parallel("styles", "core-scripts", jsFiles));
