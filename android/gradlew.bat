@echo off
setlocal
set DIR=%~dp0
if defined JAVA_HOME goto findJavaFromJavaHome
set JAVA_HOME=
:findJavaFromJavaHome
if exist "%JAVA_HOME%\bin\java.exe" goto ok
echo ERROR: JAVA_HOME is not set or points to an invalid directory.
exit /b 1
:ok
set CLASSPATH=%DIR%gradle\wrapper\gradle-wrapper.jar
"%JAVA_HOME%\bin\java.exe" %DEFAULT_JVM_OPTS% -classpath "%CLASSPATH%" org.gradle.wrapper.GradleWrapperMain %*
