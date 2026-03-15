import 'package:flutter/material.dart';

class AppLogo extends StatelessWidget {
  const AppLogo({super.key, this.size = 64});

  final double size;

  static const Color _navy = Color(0xFF080F45);
  static const Color _gold = Color(0xFFF5C518);

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: _gold,
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white, width: size * 0.045),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: _navy.withValues(alpha: 0.18),
            blurRadius: size * 0.18,
            offset: Offset(0, size * 0.06),
          ),
        ],
      ),
      child: Icon(Icons.school_rounded, color: _navy, size: size * 0.46),
    );
  }
}
