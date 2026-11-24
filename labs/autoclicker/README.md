# Auto Clicker — Android

A small Android app that taps the screen for you, built for Clash of Clans on a phone.
This folder is a complete Android project. GitHub builds the APK for you — **nothing to install on
your phone except the APK itself.**

## Where we are

**Step 1 — done when the workflow is green.** The app opens, shows a button, and says hello when you
press it. It does not tap anything yet. This step exists only to prove the build road works.

| Step | What it adds | State |
|---|---|---|
| 1 | App opens, button works | built and published |
| 2 | Accessibility permission + real taps | next |
| 3 | Saved tap points you place on screen | after that |

Nothing taps silently: step 2 adds an explicit START/STOP, and the app never runs unless you turn it on.

## Get the APK on your phone (no computer needed)

1. Open https://github.com/gret521x-coder/Gretsanti/releases/tag/apk-latest
2. Under **Assets**, tap **autoclicker-debug.apk**.
3. Android will say it is from an unknown source — allow it for your browser or file manager, then install.
4. Open **AUTO CLICKER**. The line under the title says *step 1 build* and shows your phone model and
   Android version — that is how you know the right file is running.

Every push rebuilds and replaces that same release, so the link never changes and the file is always
the newest build. The release notes carry the size, the SHA-256 and the package the APK really
contains (read by `aapt2`, not guessed).

Prefer the Actions tab? Each run also has the APK as an artifact, but that downloads a `.zip` and
needs a GitHub login.

## Build it yourself (optional, needs a computer)

```
cd labs/autoclicker
gradle assembleDebug
# APK lands in app/build/outputs/apk/debug/
```

Android Studio works too: open this folder as a project and press Run.

## Notes

- `minSdk 24`, `targetSdk 34`, Java 17, no third-party libraries.
- The debug build is signed with the standard debug key, so it installs without any keystore setup.
  A release build for sharing needs its own keystore — that comes with step 3.
- Autoclickers break Supercell's terms of service. The risk to the account is real and it is yours to
  weigh; the app itself is a plain accessibility tapper and does nothing to hide that.
