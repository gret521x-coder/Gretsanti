package com.gret.autoclicker;

import android.app.Activity;
import android.os.Build;
import android.os.Bundle;
import android.os.VibrationEffect;
import android.os.Vibrator;
import android.view.View;
import android.view.WindowManager;
import android.widget.Button;
import android.widget.TextView;

/**
 * Step 1 — the boring baby app.
 *
 * It only proves one thing: the project builds into an APK that installs and opens on a phone.
 * Nothing is tapped yet. The tapper arrives in step 2.
 */
public class MainActivity extends Activity {

    private TextView status;
    private Button button;
    private int presses = 0;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
        setContentView(R.layout.activity_main);

        status = findViewById(R.id.status);
        button = findViewById(R.id.startButton);

        button.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                presses++;
                status.setText(presses == 1
                        ? "You pressed it once. The app is alive."
                        : "You pressed it " + presses + " times. The app is alive.");
                buzz();
            }
        });

        String device = Build.MANUFACTURER + " " + Build.MODEL;
        TextView info = findViewById(R.id.deviceInfo);
        info.setText(device + "  ·  Android " + Build.VERSION.RELEASE + "  ·  step 1 build");
    }

    private void buzz() {
        try {
            Vibrator v = (Vibrator) getSystemService(VIBRATOR_SERVICE);
            if (v == null || !v.hasVibrator()) return;
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                v.vibrate(VibrationEffect.createOneShot(30, VibrationEffect.DEFAULT_AMPLITUDE));
            } else {
                v.vibrate(30);
            }
        } catch (Exception ignored) {
            // a phone without a vibrator is not a reason to crash
        }
    }
}
