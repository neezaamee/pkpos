package com.pkpos.client

import android.os.Bundle
import android.view.View
import androidx.activity.SystemBarStyle
import androidx.activity.enableEdgeToEdge
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat

class MainActivity : TauriActivity() {
  override fun onCreate(savedInstanceState: Bundle?) {
    enableEdgeToEdge(
      statusBarStyle = SystemBarStyle.dark(0xFF111827.toInt()),
      navigationBarStyle = SystemBarStyle.dark(0xFF0F172A.toInt())
    )
    super.onCreate(savedInstanceState)

    // Keep WebView safely inside system bars, camera cutouts, and soft keyboard (IME)
    val rootView: View = findViewById(android.R.id.content)
    rootView.setBackgroundColor(0xFF0F172A.toInt())
    ViewCompat.setOnApplyWindowInsetsListener(rootView) { view, windowInsets ->
      val insets = windowInsets.getInsets(
        WindowInsetsCompat.Type.systemBars() or
          WindowInsetsCompat.Type.displayCutout() or
          WindowInsetsCompat.Type.ime()
      )
      view.setPadding(insets.left, insets.top, insets.right, insets.bottom)
      WindowInsetsCompat.CONSUMED
    }
  }
}
