import 'package:flutter/material.dart';

import 'core/network/api_client.dart';
import 'listing_model_page.dart';
import 'listing_service.dart';

const kMyListingsNavy = Color(0xFF0D1B6E);
const kMyListingsDarkNavy = Color(0xFF080F45);
const kMyListingsGold = Color(0xFFF5C518);
const kMyListingsWhite = Color(0xFFFFFFFF);

class MyListingsPage extends StatefulWidget {
  const MyListingsPage({super.key});

  @override
  State<MyListingsPage> createState() => _MyListingsPageState();
}

class _MyListingsPageState extends State<MyListingsPage> {
  final ListingService _listingService = ListingService();
  final ApiClient _apiClient = ApiClient();

  bool _isLoading = true;
  String? _errorMessage;
  List<Listing> _listings = <Listing>[];

  int get _pendingCount =>
      _listings.where((listing) => listing.isModerationPending).length;

  int get _approvedCount =>
      _listings.where((listing) => listing.isModerationApproved).length;

  int get _declinedCount =>
      _listings.where((listing) => listing.isModerationDeclined).length;

  @override
  void initState() {
    super.initState();
    _loadListings();
  }

  Future<void> _loadListings({bool showLoading = true}) async {
    if (showLoading) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    } else {
      setState(() {
        _errorMessage = null;
      });
    }

    try {
      final collection = await _listingService.fetchMyListings(perPage: 50);
      if (!mounted) {
        return;
      }

      setState(() {
        _listings = collection.listings;
        _isLoading = false;
      });
    } catch (error) {
      if (!mounted) {
        return;
      }

      setState(() {
        _isLoading = false;
        _errorMessage = error is FormatException
            ? error.message
            : _apiClient.mapError(error);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF4F6FF),
      body: SafeArea(
        child: RefreshIndicator(
          color: kMyListingsNavy,
          onRefresh: () => _loadListings(showLoading: false),
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(
              parent: BouncingScrollPhysics(),
            ),
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
            children: <Widget>[
              _buildHeader(context),
              const SizedBox(height: 16),
              _buildSummary(),
              const SizedBox(height: 16),
              if (_isLoading && _listings.isEmpty)
                const SizedBox(
                  height: 260,
                  child: Center(
                    child: CircularProgressIndicator(
                      valueColor: AlwaysStoppedAnimation<Color>(
                        kMyListingsNavy,
                      ),
                    ),
                  ),
                )
              else if (_errorMessage != null && _listings.isEmpty)
                _StateCard(
                  icon: Icons.cloud_off_rounded,
                  title: 'Unable to load your listings',
                  subtitle: _errorMessage!,
                  actionLabel: 'Retry',
                  onAction: _loadListings,
                )
              else if (_listings.isEmpty)
                const _StateCard(
                  icon: Icons.storefront_outlined,
                  title: 'No posts yet',
                  subtitle:
                      'Your listings will show up here once you post your first item.',
                )
              else ...<Widget>[
                if (_errorMessage != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: _StateCard(
                      icon: Icons.error_outline,
                      title: 'Could not refresh your listings',
                      subtitle: _errorMessage!,
                      actionLabel: 'Retry',
                      onAction: _loadListings,
                    ),
                  ),
                if (_pendingCount == 0)
                  const Padding(
                    padding: EdgeInsets.only(bottom: 12),
                    child: _InlineHint(
                      icon: Icons.verified_outlined,
                      title: 'No pending items',
                      subtitle: 'Nothing is waiting for moderation right now.',
                    ),
                  ),
                if (_declinedCount == 0)
                  const Padding(
                    padding: EdgeInsets.only(bottom: 12),
                    child: _InlineHint(
                      icon: Icons.thumb_up_off_alt_outlined,
                      title: 'No declined items',
                      subtitle: 'You do not have declined listings right now.',
                    ),
                  ),
                ..._listings.map(
                  (listing) => Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: _ListingModerationCard(listing: listing),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildHeader(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(18, 18, 18, 20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: <Color>[kMyListingsDarkNavy, kMyListingsNavy],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          GestureDetector(
            onTap: () => Navigator.pop(context),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: kMyListingsWhite.withValues(alpha: 0.14),
                borderRadius: BorderRadius.circular(20),
              ),
              child: const Row(
                mainAxisSize: MainAxisSize.min,
                children: <Widget>[
                  Icon(
                    Icons.arrow_back_rounded,
                    color: kMyListingsWhite,
                    size: 16,
                  ),
                  SizedBox(width: 6),
                  Text(
                    'Back',
                    style: TextStyle(
                      color: kMyListingsWhite,
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 18),
          const Text(
            'My Listings',
            style: TextStyle(
              color: kMyListingsWhite,
              fontSize: 22,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            'Track which posts are under review, approved, or declined.',
            style: TextStyle(
              color: kMyListingsWhite.withValues(alpha: 0.78),
              fontSize: 12,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSummary() {
    return Row(
      children: <Widget>[
        Expanded(
          child: _SummaryCard(
            label: 'All Posts',
            value: _listings.length.toString(),
            accent: kMyListingsNavy,
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: _SummaryCard(
            label: 'Pending',
            value: _pendingCount.toString(),
            accent: const Color(0xFFE08A00),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: _SummaryCard(
            label: 'Approved',
            value: _approvedCount.toString(),
            accent: const Color(0xFF138A36),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: _SummaryCard(
            label: 'Declined',
            value: _declinedCount.toString(),
            accent: const Color(0xFFB3261E),
          ),
        ),
      ],
    );
  }
}

class _ListingModerationCard extends StatelessWidget {
  const _ListingModerationCard({required this.listing});

  final Listing listing;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: kMyListingsWhite,
        borderRadius: BorderRadius.circular(18),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  color: listing.color,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(
                  listing.icon,
                  color: kMyListingsNavy.withValues(alpha: 0.7),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      listing.title,
                      style: const TextStyle(
                        color: kMyListingsNavy,
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      listing.price,
                      style: const TextStyle(
                        color: kMyListingsNavy,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: <Widget>[
                        _StatusChip(
                          label: listing.moderationLabel,
                          backgroundColor: _moderationBackgroundColor(listing),
                          textColor: _moderationTextColor(listing),
                        ),
                        _StatusChip(
                          label: listing.itemStatusLabel,
                          backgroundColor: const Color(0xFFE9EEFF),
                          textColor: kMyListingsNavy,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            listing.category,
            style: TextStyle(
              color: Colors.grey[700],
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            'Condition: ${listing.condition}',
            style: TextStyle(color: Colors.grey[700], fontSize: 12),
          ),
          const SizedBox(height: 4),
          Text(
            'Reviewed: ${listing.reviewedAtLabel}',
            style: TextStyle(color: Colors.grey[700], fontSize: 12),
          ),
          if (listing.reviewedByName.trim().isNotEmpty) ...<Widget>[
            const SizedBox(height: 4),
            Text(
              'Reviewed by: ${listing.reviewedByName}',
              style: TextStyle(color: Colors.grey[700], fontSize: 12),
            ),
          ],
          if (listing.isModerationDeclined &&
              listing.adminNote.trim().isNotEmpty) ...<Widget>[
            const SizedBox(height: 12),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFFFF0EE),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  const Text(
                    'Admin note',
                    style: TextStyle(
                      color: Color(0xFF8A1C12),
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    listing.adminNote,
                    style: const TextStyle(
                      color: Color(0xFF8A1C12),
                      fontSize: 12,
                      height: 1.4,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Color _moderationBackgroundColor(Listing listing) {
    if (listing.isModerationApproved) {
      return const Color(0xFFE8F7EC);
    }
    if (listing.isModerationDeclined) {
      return const Color(0xFFFFE9E7);
    }

    return const Color(0xFFFFF5E1);
  }

  Color _moderationTextColor(Listing listing) {
    if (listing.isModerationApproved) {
      return const Color(0xFF138A36);
    }
    if (listing.isModerationDeclined) {
      return const Color(0xFFB3261E);
    }

    return const Color(0xFFE08A00);
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({
    required this.label,
    required this.value,
    required this.accent,
  });

  final String label;
  final String value;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 10),
      decoration: BoxDecoration(
        color: kMyListingsWhite,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        children: <Widget>[
          Text(
            value,
            style: TextStyle(
              color: accent,
              fontSize: 20,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            textAlign: TextAlign.center,
            style: TextStyle(color: Colors.grey[600], fontSize: 11),
          ),
        ],
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({
    required this.label,
    required this.backgroundColor,
    required this.textColor,
  });

  final String label;
  final Color backgroundColor;
  final Color textColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: textColor,
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _InlineHint extends StatelessWidget {
  const _InlineHint({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: kMyListingsWhite,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(icon, color: kMyListingsNavy, size: 18),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  title,
                  style: const TextStyle(
                    color: kMyListingsNavy,
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  style: TextStyle(color: Colors.grey[600], fontSize: 12),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _StateCard extends StatelessWidget {
  const _StateCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final String? actionLabel;
  final Future<void> Function({bool showLoading})? onAction;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: kMyListingsWhite,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(icon, color: kMyListingsNavy, size: 22),
          const SizedBox(height: 10),
          Text(
            title,
            style: const TextStyle(
              color: kMyListingsNavy,
              fontSize: 14,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            subtitle,
            style: TextStyle(color: Colors.grey[600], fontSize: 12),
          ),
          if (actionLabel != null && onAction != null) ...<Widget>[
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: () => onAction!.call(),
              style: ElevatedButton.styleFrom(
                backgroundColor: kMyListingsNavy,
                foregroundColor: kMyListingsWhite,
              ),
              child: Text(actionLabel!),
            ),
          ],
        ],
      ),
    );
  }
}
