import 'dart:io';
import 'package:flutter/material.dart';

// ─── Listing Model ────────────────────────────────────────────────────────────
class Listing {
  final int id;
  final String title;
  final String price;
  final String category;
  final String condition;
  final String description;
  final String seller;
  final String sellerAvatar;
  final IconData icon;
  final Color color;
  final File? imageFile;

  const Listing({
    required this.id,
    required this.title,
    required this.price,
    required this.category,
    required this.condition,
    required this.description,
    required this.seller,
    required this.sellerAvatar,
    required this.icon,
    required this.color,
    this.imageFile,
  });
}

// ─── Dummy Listings ───────────────────────────────────────────────────────────
final List<Listing> dummyListings = [];