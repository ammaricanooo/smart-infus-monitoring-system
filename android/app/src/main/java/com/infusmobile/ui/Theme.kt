package com.infusmobile.ui

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Typography
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

// Tailwind CSS Palette
object InfusColors {
    val Blue900 = Color(0xFF1E3A8A)
    val Blue700 = Color(0xFF1D4ED8)
    val Blue500 = Color(0xFF3B82F6)
    val Blue50 = Color(0xFFEFF6FF)
    
    val SuccessGreen = Color(0xFF16A34A)
    val SuccessBg = Color(0xFFDCFCE7)
    
    val WarningOrange = Color(0xFFF97316)
    val WarningBg = Color(0xFFFFEDD5)
    
    val ErrorRed = Color(0xFFDC2626)
    val ErrorBg = Color(0xFFFEE2E2)
    
    val Slate900 = Color(0xFF0F172A)
    val Slate700 = Color(0xFF334155)
    val Slate500 = Color(0xFF64748B)
    val Slate300 = Color(0xFFCBD5E1)
    val Slate100 = Color(0xFFF1F5F9)
    val Slate50 = Color(0xFFF8FAFC)
}

private val LightColors = lightColorScheme(
    primary = InfusColors.Blue900,
    onPrimary = Color.White,
    secondary = InfusColors.Blue500,
    background = InfusColors.Slate50,
    surface = Color.White,
    onSurface = InfusColors.Slate900,
    onBackground = InfusColors.Slate900,
)

@Composable
fun InfusTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = LightColors,
        typography = Typography(),
        content = content
    )
}
